<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the combined-constructor slice of the "Primary + Named Constructors"
 * standard (#34, slice 2 — scoped on #193).
 *
 * A primary constructor must initialize exactly one way. A `__construct` whose
 * body branches on a *construction-mode signal* is several constructors merged
 * into one, and belongs as named constructors that each route through a single,
 * unconditional primary constructor. Three signals are token-visible inside one
 * file, and each reports under its own code so a consuming ruleset can tune
 * them separately:
 *
 * - `ModeFlag` — a parameter typed `bool` (or defaulting to `true`/`false`)
 *   used in the condition of an `if`, `elseif`, `switch`, `match`, or ternary.
 * - `TypeSwitch` — a parameter tested with `instanceof` or a type-predicate
 *   call (`is_string()`, `is_array()`, `gettype()`, …) in such a condition. A
 *   union-typed parameter strengthens the signal but is not required for it, so
 *   the parameter's own declared type is never consulted.
 * - `ArgumentCount` — `func_num_args()` or `func_get_args()` anywhere in the
 *   body. Poor-man's overloading needs no branch to be a mode signal; reading
 *   the argument count *is* the mode switch.
 *
 * A construct's "condition" is read the way each construct spells it: the
 * parenthesised expression of an `if`, `elseif`, `switch`, or `match`; the
 * expression in front of a ternary `?`; and — for the two dispatch idioms that
 * put the test in the branch rather than the head — a `match` arm's condition
 * and a `switch`'s `case` labels. `switch (true) { case is_string($v): … }` is
 * the same construction switch as `if (is_string($v))`, so it reports alike.
 *
 * Every violation is a **warning**, never an error. Branching in a constructor
 * is a design smell rather than a defect, so the sniff points at
 * split-into-named-constructors candidates and leaves the call to review.
 * Detection only: splitting a constructor rewrites the class's construction API
 * and every call site, which is not a mechanical rewrite.
 *
 * Reported at the *parameter variable* for the first two signals — the thing
 * being switched on — and at the function name for the third.
 *
 * Deliberately **not** reported, and why:
 *
 * - **Guard clauses.** A branch whose first statement is a `throw` is
 *   validating a precondition, not selecting an initialization path, so its
 *   condition is exempt whatever signal it carries (#193's design constraint,
 *   stated for all three signals). "Only throws" is tested as "the first
 *   statement is a `throw`", because anything after one is unreachable.
 * - **Coalesce defaults.** `$this->x = $x ?? new Default();` carries no
 *   branching token at all, and the elvis `?:` is excluded explicitly: a
 *   `T_INLINE_THEN` immediately followed by a `T_INLINE_ELSE` supplies a
 *   default for one expression rather than selecting between two.
 * - **`is_null()`.** Left out of the predicate list on the same grounds:
 *   `if (is_null($x)) { $x = new Default(); }` is a spelling of a coalesce
 *   default, not a test of which type arrived.
 * - **Anything but `__construct`.** Named constructors and ordinary methods are
 *   out of scope; the named-constructor side of the standard is #184's sniff.
 *   A `function __construct()` that is not a class member is not a constructor
 *   either, so the declaration's immediately enclosing scope must be class-like.
 * - **Bodiless constructors.** An abstract or interface declaration has no body
 *   to walk, and a promotion-only constructor has no statements in it.
 * - **Nested declarations.** A closure, arrow function, or anonymous class
 *   declared in the body runs on its own terms — `func_get_args()` inside a
 *   closure reads the *closure's* arguments — so every nested declaration scope
 *   is jumped rather than walked into.
 *
 * See docs/standards/constructors-primary-named-constructors.md.
 */
class DisallowCombinedConstructorSniff implements Sniff
{
    /**
     * Scopes whose direct member functions are methods, so a `__construct`
     * declared in one is a real constructor.
     *
     * @var array<int, int|string>
     */
    private const CLASS_LIKE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT, T_INTERFACE, T_ENUM];

    /**
     * Declarations whose bodies are not constructor code.
     *
     * @var array<int, int|string>
     */
    private const NESTED_DECLARATIONS = [T_FUNCTION, T_CLOSURE, T_FN, T_ANON_CLASS];

    /**
     * Control structures that own a parenthesised condition. A token inside one
     * of these parenthesis pairs sits in a branching condition.
     *
     * @var array<int, int|string>
     */
    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_SWITCH, T_MATCH];

    /**
     * Calls that answer "what type is this?" rather than "what value is this?".
     *
     * `is_null()` is deliberately absent — see the class docblock. The list is
     * matched case-insensitively, as PHP resolves function names.
     *
     * @var array<int, string>
     */
    private const TYPE_PREDICATES = [
        'get_class',
        'get_debug_type',
        'gettype',
        'is_a',
        'is_array',
        'is_bool',
        'is_callable',
        'is_countable',
        'is_double',
        'is_float',
        'is_int',
        'is_integer',
        'is_iterable',
        'is_long',
        'is_numeric',
        'is_object',
        'is_resource',
        'is_scalar',
        'is_string',
        'is_subclass_of',
    ];

    /**
     * Tokens that end the expression a mode signal belongs to, so a selector
     * found after one of them belongs to a different expression. A brace is
     * among them: a block opening after the expression is a body, never a
     * continuation of it.
     *
     * @var array<int, int|string>
     */
    private const EXPRESSION_TERMINATORS = [
        T_COLON,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_SEMICOLON,
    ];

    /**
     * The two ways to read the argument list a caller actually supplied.
     *
     * @var array<int, string>
     */
    private const ARGUMENT_READERS = ['func_get_args', 'func_num_args'];

    /**
     * Tokens that mean the following T_STRING names a member or a class rather
     * than a plain function, so it is not the global function it resembles.
     *
     * @var array<int, int|string>
     */
    private const NAME_QUALIFIERS = [
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || strtolower($name) !== '__construct') {
            return;
        }

        $enclosing = $tokens[$stackPtr]['conditions'];

        if (!in_array(end($enclosing), self::CLASS_LIKE_SCOPES, true)) {
            return;
        }

        // An abstract or interface constructor has no body to walk. A
        // promotion-only constructor has one, and simply holds no statements.
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $parameters = $this->parameterTypes($phpcsFile, $stackPtr);
        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::NESTED_DECLARATIONS, true) && isset($tokens[$pointer]['scope_closer'])) {
                $pointer = $tokens[$pointer]['scope_closer'];

                continue;
            }

            if ($code === T_STRING && $this->isArgumentReader($phpcsFile, $pointer)) {
                $phpcsFile->addWarning(
                    'Reading the constructor\'s own argument list with %s() overloads __construct()'
                        . ' into several constructors; give each construction scenario its own named'
                        . ' constructor delegating to one primary constructor'
                        . ' (see docs/standards/constructors-primary-named-constructors.md)',
                    $pointer,
                    'ArgumentCount',
                    [$tokens[$pointer]['content']]
                );

                continue;
            }

            if ($code === T_VARIABLE && array_key_exists($tokens[$pointer]['content'], $parameters)) {
                $this->reportModeSwitch($phpcsFile, $pointer, $closer, $parameters);
            }
        }
    }

    /**
     * The constructor's parameters, mapped from variable name to whether the
     * parameter is a boolean flag.
     *
     * A parameter is a flag when its native type declaration resolves to plain
     * `bool` (`bool`, `?bool`, `bool|null`) or its default is the literal
     * `true`/`false` — the same test CleanCode.Functions.DisallowBooleanArgumentFlag
     * applies, so the two sniffs agree on what a flag is. A wider union such as
     * `bool|string` carries a value, not a branch selector.
     *
     * A variadic parameter is a list, never a single mode signal, and is left
     * out of the map altogether.
     *
     * @return array<string, bool>
     */
    private function parameterTypes(File $phpcsFile, int $stackPtr): array
    {
        $parameters = [];

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if ($parameter['variable_length'] === true) {
                continue;
            }

            $normalized = ltrim(strtolower(preg_replace('/\s+/', '', (string) $parameter['type_hint']) ?? ''), '?');
            $types = array_values(array_diff(explode('|', $normalized), ['null', '']));
            $default = strtolower(trim((string) ($parameter['default'] ?? '')));

            $parameters[$parameter['name']] = $types === ['bool']
                || in_array($default, ['true', 'false'], true);
        }

        return $parameters;
    }

    /**
     * Reports one use of a parameter, when that use is a mode signal sitting in
     * a branching condition that is not a guard clause.
     *
     * @param array<string, bool> $parameters
     */
    private function reportModeSwitch(File $phpcsFile, int $pointer, int $closer, array $parameters): void
    {
        $isTypeTest = $this->isTypeTested($phpcsFile, $pointer);

        if (!$isTypeTest && $parameters[$phpcsFile->getTokens()[$pointer]['content']] === false) {
            return;
        }

        $branch = $this->branchOwner($phpcsFile, $pointer, $closer);

        if ($branch === null || $this->isGuardClause($phpcsFile, $branch)) {
            return;
        }

        $name = $phpcsFile->getTokens()[$pointer]['content'];

        if ($isTypeTest) {
            $phpcsFile->addWarning(
                'Branching on the runtime type of %s combines several constructors into'
                    . ' __construct(); give each accepted type its own named constructor'
                    . ' delegating to one primary constructor'
                    . ' (see docs/standards/constructors-primary-named-constructors.md)',
                $pointer,
                'TypeSwitch',
                [$name]
            );

            return;
        }

        $phpcsFile->addWarning(
            'Branching on the mode flag %s combines several constructors into __construct();'
                . ' give each mode its own named constructor delegating to one primary'
                . ' constructor'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $pointer,
            'ModeFlag',
            [$name]
        );
    }

    /**
     * Whether this parameter use is a test of its runtime type — the left
     * operand of `instanceof`, or the direct argument of a type-predicate call.
     *
     * "Direct argument" is exact: the innermost parenthesis pair around the
     * variable must be the predicate's own call parentheses, so
     * `is_string(trim($value))` tests a derived value rather than the parameter.
     */
    private function isTypeTested(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(T_WHITESPACE, $pointer + 1, null, true);

        if ($next !== false && $tokens[$next]['code'] === T_INSTANCEOF) {
            return true;
        }

        $nesting = $tokens[$pointer]['nested_parenthesis'] ?? [];

        if ($nesting === []) {
            return false;
        }

        $openers = array_keys($nesting);
        $opener = (int) end($openers);
        $callee = $phpcsFile->findPrevious(T_WHITESPACE, $opener - 1, null, true);

        return $callee !== false
            && $tokens[$callee]['code'] === T_STRING
            && in_array(strtolower($tokens[$callee]['content']), self::TYPE_PREDICATES, true)
            && $this->isPlainFunctionCall($phpcsFile, $callee);
    }

    /**
     * Whether a T_STRING names one of the argument-list readers, called as the
     * global function rather than as a member of something.
     */
    private function isArgumentReader(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (!in_array(strtolower($tokens[$pointer]['content']), self::ARGUMENT_READERS, true)) {
            return false;
        }

        $next = $phpcsFile->findNext(T_WHITESPACE, $pointer + 1, null, true);

        return $next !== false
            && $tokens[$next]['code'] === T_OPEN_PARENTHESIS
            && $this->isPlainFunctionCall($phpcsFile, $pointer);
    }

    /**
     * Whether nothing qualifies this name into a member or a class — no `->`,
     * `?->`, `::`, `new`, or `function` in front of it.
     */
    private function isPlainFunctionCall(File $phpcsFile, int $pointer): bool
    {
        $previous = $phpcsFile->findPrevious(T_WHITESPACE, $pointer - 1, null, true);

        return $previous === false
            || !in_array($phpcsFile->getTokens()[$previous]['code'], self::NAME_QUALIFIERS, true);
    }

    /**
     * The branching construct whose condition holds this token, or null when
     * the token is not in a condition at all.
     *
     * Two shapes carry a condition, and each is recognised on its own terms:
     *
     * - A parenthesised condition — `if`, `elseif`, `switch`, `match` — is
     *   found by walking the token's enclosing parenthesis pairs inward-out and
     *   taking the innermost one PHPCS attributes to such an owner. This is
     *   what makes a spaced `else if` behave like `elseif`: the parentheses
     *   belong to the `T_IF`, and the `T_ELSE` in front of it is never
     *   consulted.
     * - A selector *following* the expression — a ternary `?` or a `match` arm
     *   `=>` — has no such marker, so the scan runs forward from the token to
     *   the first selector or statement boundary, jumping bracketed and braced
     *   groups whole and stepping out of a group that closes around it (which
     *   is what makes `is_string($value) ? … : …` a condition).
     *
     * @return int|null Pointer to the owning `if`/`elseif`/`switch`/`match`, the
     *                  ternary `?`, or the `match` arm `=>`.
     */
    private function branchOwner(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();

        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        foreach (array_reverse($openers) as $opener) {
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if ($owner !== null && in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true)) {
                return (int) $owner;
            }
        }

        return $this->followingSelector($phpcsFile, $pointer, $closer);
    }

    /**
     * The ternary `?` or `match` arm `=>` this token's expression feeds, or null
     * when the expression ends without reaching one.
     */
    private function followingSelector(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($next = $pointer + 1; $next < $closer; $next++) {
            $code = $tokens[$next]['code'];

            if ($code === T_MATCH_ARROW) {
                return $next;
            }

            // `?:` supplies a default for one expression rather than selecting
            // between two, so it is not a branch.
            if ($code === T_INLINE_THEN) {
                $following = $phpcsFile->findNext(T_WHITESPACE, $next + 1, null, true);

                return $following !== false && $tokens[$following]['code'] === T_INLINE_ELSE ? null : $next;
            }

            if ($code === T_COLON && $this->isCaseColon($phpcsFile, $next)) {
                return $this->enclosingSwitch($phpcsFile, $pointer);
            }

            if (in_array($code, self::EXPRESSION_TERMINATORS, true)) {
                return null;
            }

            // A group opening here belongs to the expression — jump it whole so
            // its contents cannot be mistaken for the expression's own tokens.
            // Only brackets are jumped: a brace ends the expression outright, so
            // it is a terminator above rather than something to step over.
            foreach (['parenthesis_closer', 'bracket_closer'] as $key) {
                if (isset($tokens[$next][$key]) && $tokens[$next][$key] > $next) {
                    $next = (int) $tokens[$next][$key];

                    break;
                }
            }
        }

        return null;
    }

    /**
     * Whether this colon closes a `case`/`default` label rather than a ternary,
     * a named argument, a return type, or an alternative-syntax block.
     */
    private function isCaseColon(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $tokens[$pointer]['scope_condition'] ?? null;

        return $owner !== null && in_array($tokens[$owner]['code'], [T_CASE, T_DEFAULT], true);
    }

    /**
     * The `switch` a case label belongs to — the innermost one holding this
     * token — or null when there is none.
     */
    private function enclosingSwitch(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_reverse($tokens[$pointer]['conditions'] ?? [], true) as $owner => $code) {
            if ($code === T_SWITCH) {
                return (int) $owner;
            }
        }

        return null;
    }

    /**
     * Whether the branch this construct selects is a precondition guard rather
     * than an initialization path — its first statement is a `throw`.
     *
     * A `switch` is a guard only when every one of its non-empty case bodies
     * throws, since one condition serves them all; an empty fall-through case
     * neither qualifies nor disqualifies it.
     */
    private function isGuardClause(File $phpcsFile, int $branch): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$branch]['code'];

        if ($code === T_SWITCH) {
            return $this->everyCaseThrows($phpcsFile, $branch);
        }

        if ($code === T_INLINE_THEN || $code === T_MATCH_ARROW) {
            return $this->firstStatementThrows($phpcsFile, $branch);
        }

        // `match` reached here through its subject parentheses: the whole block
        // is the branch, and its arms are checked one by one.
        if ($code === T_MATCH) {
            return $this->everyArmThrows($phpcsFile, $branch);
        }

        // A brace-less `if`/`elseif` body has no scope_opener, so the condition's
        // closing parenthesis is where the branch starts instead.
        $start = $tokens[$branch]['scope_opener'] ?? $tokens[$branch]['parenthesis_closer'] ?? null;

        return $start !== null && $this->firstStatementThrows($phpcsFile, (int) $start);
    }

    /**
     * Whether the first statement after this token is a `throw`. Anything after
     * a `throw` is unreachable, so this is the whole of "the body only throws".
     */
    private function firstStatementThrows(File $phpcsFile, int $from): bool
    {
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $from + 1, null, true);

        return $first !== false && $phpcsFile->getTokens()[$first]['code'] === T_THROW;
    }

    /**
     * Whether every non-empty `case`/`default` body of this switch throws first.
     */
    private function everyCaseThrows(File $phpcsFile, int $switch): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$switch]['scope_opener'], $tokens[$switch]['scope_closer'])) {
            return false;
        }

        $end = $tokens[$switch]['scope_closer'];
        $bodies = 0;

        for ($pointer = $tokens[$switch]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if (!in_array($tokens[$pointer]['code'], [T_CASE, T_DEFAULT], true)) {
                continue;
            }

            if (!isset($tokens[$pointer]['scope_opener'])) {
                continue;
            }

            $opener = $tokens[$pointer]['scope_opener'];
            $first = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, $end, true);

            // An empty fall-through case has no body of its own to judge.
            if ($first === false || in_array($tokens[$first]['code'], [T_CASE, T_DEFAULT], true)) {
                continue;
            }

            if ($tokens[$first]['code'] !== T_THROW) {
                return false;
            }

            $bodies++;
        }

        return $bodies > 0;
    }

    /**
     * Whether every arm of this `match` throws — the `match` equivalent of a
     * switch whose cases all guard.
     */
    private function everyArmThrows(File $phpcsFile, int $match): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$match]['scope_opener'], $tokens[$match]['scope_closer'])) {
            return false;
        }

        $end = $tokens[$match]['scope_closer'];
        $arms = 0;

        for ($pointer = $tokens[$match]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if ($tokens[$pointer]['code'] !== T_MATCH_ARROW) {
                continue;
            }

            if (!$this->firstStatementThrows($phpcsFile, $pointer)) {
                return false;
            }

            $arms++;
        }

        return $arms > 0;
    }
}
