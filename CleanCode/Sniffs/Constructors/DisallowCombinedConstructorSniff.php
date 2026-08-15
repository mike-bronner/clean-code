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
 *   the argument count *is* the mode switch. A read in a guard clause's
 *   condition is still exempt, like every other signal.
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
 * Three invariants hold across every token walk below, and each is
 * mutation-tested in the test file rather than assumed:
 *
 * - **Comment tolerance.** Every adjacency test skips `Tokens::$emptyTokens`,
 *   never `T_WHITESPACE` alone, so a comment interleaved between two tokens
 *   that must be adjacent cannot flip a verdict — in either direction. A
 *   comment before an `instanceof`, before a predicate's call parentheses, or
 *   before an argument reader's parentheses still reports; a comment after an
 *   object operator, or between a ternary `?` and its `:`, still does not.
 * - **Argument totality.** A parameter counts as a type predicate's subject
 *   only when it is the bare, undecorated first argument — see
 *   {@see self::isBareFirstArgument()}.
 * - **Expression levels.** A forward scan reads a token as its expression's own
 *   only when the token belongs to that expression rather than to a construct
 *   nested in it or wrapped around it. A group standing in the way is jumped
 *   whole, whatever kind of group it is — parentheses, brackets, or the braces
 *   of a `match` used as an operand ({@see self::groupEnd()}) — and what a comma
 *   means is settled by the group holding it rather than by what follows it, in
 *   one pass over the body ({@see self::buildCommaMap()}). That pass is also
 *   what keeps the walk linear: every position a scan steps on keeps that
 *   scan's answer ({@see self::remember()}), so a constructor that uses one
 *   parameter many times in one expression is walked once, not once per use.
 *
 * Deliberately **not** reported, and why:
 *
 * - **Guard clauses.** A `throw` rejects a call rather than choosing how to
 *   build one, so a condition is exempt when the construct it belongs to has no
 *   more than one surviving construction path — whatever signal the condition
 *   carries, and whichever side of the branch the `throw` is written on
 *   ({@see self::isGuardClause()}; #193's design constraint, stated for all
 *   three signals). "Only throws" is tested as "the first statement is a
 *   `throw`", because anything after one is unreachable.
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
 * - **Nested declarations.** A named function, closure, arrow function, or
 *   anonymous class declared in the body runs on its own terms —
 *   `func_get_args()` inside a closure reads the *closure's* arguments — so
 *   every nested declaration scope is jumped rather than walked into.
 * - **Named-argument predicate calls.** `is_a(object: $source, class: $c)`
 *   addresses its subject by name rather than by position, and resolving that
 *   needs a per-predicate table of parameter names. Staying silent costs a
 *   missed warning on an exotic spelling rather than a wrong one on a common
 *   spelling — see {@see self::isBareFirstArgument()}.
 *
 * See docs/standards/constructors-primary-named-constructors.md.
 */
class DisallowCombinedConstructorSniff implements Sniff
{
    /**
     * Scopes whose direct member functions are methods, so a `__construct`
     * declared in one is a real constructor with a body to walk.
     *
     * The two class-like scopes missing from the list are missing because PHP
     * cannot put a constructor body in either: `interface` allows the
     * declaration but no body ("Interface function … cannot contain body"), and
     * `enum` rejects the declaration outright ("Enum … cannot include magic
     * method __construct"). An entry for either would be one no fixture could
     * ever exercise, since neither can be spelled in code that runs.
     *
     * @var array<int, int|string>
     */
    private const CLASS_LIKE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT];

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
     * Tokens that end the expression a mode signal belongs to outright, so a
     * selector found after one of them belongs to a different expression. A
     * brace is among them: a block opening after the expression is a body,
     * never a continuation of it.
     *
     * Two punctuation marks are deliberately absent. A comma separates the
     * elements *inside* a group rather than ending the expression that group's
     * own value feeds, and is handled by {@see self::enclosingGroupCloser()}
     * instead. A `T_COLON` needs no entry either: the only ones the scan can
     * reach are a `case` label's, read as a condition of its own before this
     * list is consulted, and an alternative-syntax block's, which opens a scope
     * {@see self::groupEnd()} steps over whole. (A ternary's `:` is a
     * `T_INLINE_ELSE`, and never a `T_COLON`.)
     *
     * @var array<int, int|string>
     */
    private const EXPRESSION_TERMINATORS = [
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
     * `T_FUNCTION` is deliberately absent, unlike the sibling
     * DisallowDebugFunctionsSniff's list: that sniff registers on every
     * T_STRING in a file, while this one only ever reaches a name inside a
     * constructor body whose nested declarations {@see self::process()} jumps at
     * the declaration keyword, before the declared name. A `T_FUNCTION` entry
     * here could never be read. `T_BITWISE_AND` (`function &is_string()`) is
     * absent for the same reason.
     *
     * A namespace separator is not in the list either, because it does not
     * answer the question on its own: `\is_string()` is the global function,
     * while `App\is_string()` is not — see {@see self::isQualifiedName()}.
     *
     * @var array<int, int|string>
     */
    private const NAME_QUALIFIERS = [
        T_DOUBLE_COLON,
        T_NEW,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    /**
     * Tokens that open a group holding a sub-expression: a call's or a
     * grouping's parentheses, an array literal or a subscript's brackets, and
     * the braces of a `match` arm list or a block.
     *
     * Read while building the comma map ({@see self::buildCommaMap()}), which
     * keys on the *opening* token so it can pair it with that token's own
     * `parenthesis_closer`/`bracket_closer`. A closer taken from an opening
     * token is the group's own; a `scope_closer` read from a construct keyword
     * is not always — PHP_CodeSniffer gives an arrow function that ends a
     * comma-separated group the *group's* closing token as its own
     * `scope_closer`, so a map built from `scope_closer` would lose the group.
     *
     * @var array<int, int|string>
     */
    private const GROUP_OPENERS = [
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
    ];

    /**
     * Where the forward scan resumes at each comma of the constructor being
     * walked, keyed by the comma's own pointer. Built once per constructor by
     * {@see self::buildCommaMap()}; a comma absent from the map ends the
     * expression.
     *
     * @var array<int, int>
     */
    private array $commaTargets = [];

    /**
     * The selector each already-scanned position feeds, keyed by position.
     *
     * The forward scan is deterministic and reads nothing behind its own
     * starting point, so every position it steps on has the same answer as the
     * scan that reached it. Recording all of them turns what would otherwise be
     * one full-length scan per parameter use — quadratic on a constructor that
     * uses one parameter many times in a single expression — into one scan per
     * position over the whole constructor.
     *
     * Holds `null` for a position that reaches no selector, so membership is
     * tested with array_key_exists() rather than isset().
     *
     * @var array<int, int|null>
     */
    private array $selectorCache = [];

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

        // Both maps describe this constructor's body alone, and the scan's
        // answers depend on where that body ends, so neither survives into the
        // next constructor.
        $this->selectorCache = [];
        $this->buildCommaMap($phpcsFile, $tokens[$stackPtr]['scope_opener'], $closer);

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::NESTED_DECLARATIONS, true) && isset($tokens[$pointer]['scope_closer'])) {
                $pointer = $tokens[$pointer]['scope_closer'];

                continue;
            }

            if ($code === T_STRING && $this->isArgumentReader($phpcsFile, $pointer)) {
                $this->reportArgumentReader($phpcsFile, $pointer, $closer);

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
     * Reports one read of the constructor's own argument list.
     *
     * The read needs no branch to be a mode signal, so it reports wherever it
     * sits — unless it sits in the condition of a guard clause, which is exempt
     * for this signal exactly as it is for the other two: a branch whose body
     * only throws is rejecting a call rather than choosing how to build one.
     */
    private function reportArgumentReader(File $phpcsFile, int $pointer, int $closer): void
    {
        $branch = $this->branchOwner($phpcsFile, $pointer, $closer);

        if ($branch !== null && $this->isGuardClause($phpcsFile, $branch)) {
            return;
        }

        $phpcsFile->addWarning(
            'Reading the constructor\'s own argument list with %s() overloads __construct()'
                . ' into several constructors; give each construction scenario its own named'
                . ' constructor delegating to one primary constructor'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $pointer,
            'ArgumentCount',
            [$phpcsFile->getTokens()[$pointer]['content']]
        );
    }

    /**
     * Reports one use of a parameter, when that use is a mode signal sitting in
     * a branching condition that is not a guard clause.
     *
     * @param array<string, bool> $parameters
     */
    private function reportModeSwitch(File $phpcsFile, int $pointer, int $closer, array $parameters): void
    {
        $typeTested = $this->isTypeTested($phpcsFile, $pointer);

        if (!$typeTested && $parameters[$phpcsFile->getTokens()[$pointer]['content']] === false) {
            return;
        }

        $branch = $this->branchOwner($phpcsFile, $pointer, $closer);

        if ($branch === null || $this->isGuardClause($phpcsFile, $branch)) {
            return;
        }

        $name = $phpcsFile->getTokens()[$pointer]['content'];

        if ($typeTested) {
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
     * "Direct argument" is exact twice over: the innermost parenthesis pair
     * around the variable must be the predicate's own call parentheses, so
     * `is_string(trim($value))` tests a derived value rather than the parameter;
     * and the variable must be the whole of the *first* argument, the only one
     * any of these predicates takes as its subject — see
     * {@see self::isBareFirstArgument()}. The two-argument spellings
     * `is_a($value, $expectedClass)` and `is_subclass_of($value, $expectedClass)`
     * test `$value` alone — the class name they compare it against is a value
     * the call reads, never a parameter whose own type is being switched on.
     */
    private function isTypeTested(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if ($next !== false && $tokens[$next]['code'] === T_INSTANCEOF) {
            return true;
        }

        $nesting = $tokens[$pointer]['nested_parenthesis'] ?? [];

        if ($nesting === []) {
            return false;
        }

        $openers = array_keys($nesting);
        $opener = (int) end($openers);
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        return $callee !== false
            && $tokens[$callee]['code'] === T_STRING
            && in_array(strtolower($tokens[$callee]['content']), self::TYPE_PREDICATES, true)
            && $this->isPlainFunctionCall($phpcsFile, $callee)
            && $this->isBareFirstArgument($phpcsFile, $opener, (int) $nesting[$opener], $pointer);
    }

    /**
     * Whether this token is the *whole* of the first argument of the call
     * opening at $opener — the bare parameter itself, undecorated.
     *
     * Totality is the point, not mere precedence. Confirming that no argument
     * separator *precedes* the token says nothing about what the call actually
     * tests: `is_string($obj->prop)`, `is_string($items[$key])` and
     * `is_a(class: $class, object: $source)` all put a parameter in the first
     * argument's span without that parameter being the subject. So the token
     * must be flanked by the call's own punctuation on both sides — the opening
     * parenthesis in front of it, and either the argument separator or the
     * call's closing parenthesis behind it. Any other neighbour means the
     * subject is a derived value, a subscript, or another argument entirely.
     *
     * A named-argument call therefore reports nothing at all: `object:` in front
     * of the subject is not the opening parenthesis. That is deliberate —
     * resolving a named argument needs a per-predicate table of parameter names,
     * and staying silent on an exotic spelling costs a missed warning rather
     * than a wrong one.
     */
    private function isBareFirstArgument(File $phpcsFile, int $opener, int $closer, int $pointer): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return $before === $opener
            && $after !== false
            && ($after === $closer || $phpcsFile->getTokens()[$after]['code'] === T_COMMA);
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

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return $next !== false
            && $tokens[$next]['code'] === T_OPEN_PARENTHESIS
            && $this->isPlainFunctionCall($phpcsFile, $pointer);
    }

    /**
     * Whether this name is the global function it reads as, rather than
     * something that merely shares its spelling — a member (`->`, `?->`, `::`),
     * an instantiation (`new`), or a name in another namespace.
     */
    private function isPlainFunctionCall(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);

        if ($previous === false) {
            return true;
        }

        if (in_array($tokens[$previous]['code'], self::NAME_QUALIFIERS, true)) {
            return false;
        }

        return $tokens[$previous]['code'] !== T_NS_SEPARATOR || !$this->isQualifiedName($phpcsFile, $previous);
    }

    /**
     * Whether the separator at this pointer makes the name behind it a
     * *qualified* one — `App\Utils\is_string()`, `namespace\func_get_args()` —
     * rather than the fully-qualified spelling of a global function,
     * `\is_string()`.
     *
     * A qualified name resolves outside the global namespace, so it is never
     * the global function this sniff reads. The same test the sibling
     * DisallowDebugFunctionsSniff applies, so the two agree on what counts as a
     * global call.
     */
    private function isQualifiedName(File $phpcsFile, int $separator): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $separator - 1, null, true);

        return $before !== false
            && in_array($phpcsFile->getTokens()[$before]['code'], [T_STRING, T_NAMESPACE], true);
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
     *   the first selector or expression boundary, jumping groups whole and
     *   stepping out of a group that closes around it (which is what makes
     *   `is_string($value) ? … : …` a condition).
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
     *
     * The scan reads a token as the expression's own only when the token sits
     * at the expression's level. Two rules keep it there, and both are stated
     * about *any* nesting rather than about the shapes that first needed them:
     *
     * - A group opening in front of the scan is jumped whole, whatever kind of
     *   group it is — see {@see self::groupEnd()}.
     * - A comma is a separator inside an enclosing group rather than an end,
     *   so the scan steps out to that group's closer and carries on with the
     *   group's own value — see {@see self::enclosingGroupCloser()}.
     */
    private function followingSelector(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $visited = [];

        for ($next = $pointer + 1; $next < $closer; $next++) {
            if (array_key_exists($next, $this->selectorCache)) {
                return $this->remember($visited, $this->selectorCache[$next]);
            }

            $visited[] = $next;
            $code = $tokens[$next]['code'];

            if ($code === T_MATCH_ARROW) {
                return $this->remember($visited, $next);
            }

            // `?:` supplies a default for one expression rather than selecting
            // between two, so it is not a branch.
            if ($code === T_INLINE_THEN) {
                $following = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
                $elvis = $following !== false && $tokens[$following]['code'] === T_INLINE_ELSE;

                return $this->remember($visited, $elvis ? null : $next);
            }

            if ($code === T_COLON && $this->isCaseColon($phpcsFile, $next)) {
                return $this->remember($visited, $tokens[$next]['scope_condition']);
            }

            if ($code === T_COMMA) {
                if (!isset($this->commaTargets[$next])) {
                    return $this->remember($visited, null);
                }

                $next = $this->commaTargets[$next];

                continue;
            }

            if (in_array($code, self::EXPRESSION_TERMINATORS, true)) {
                return $this->remember($visited, null);
            }

            // A group opening here belongs to the expression — jump it whole so
            // its contents cannot be mistaken for the expression's own tokens.
            $next = $this->groupEnd($phpcsFile, $next) ?? $next;
        }

        return $this->remember($visited, null);
    }

    /**
     * Records one scan's answer against every position that scan stepped on,
     * and hands the answer back.
     *
     * @param array<int, int> $visited
     */
    private function remember(array $visited, ?int $selector): ?int
    {
        foreach ($visited as $position) {
            $this->selectorCache[$position] = $selector;
        }

        return $selector;
    }

    /**
     * Records where the forward scan resumes at each comma in this body.
     *
     * A comma means one of three things, and which one it is depends on the
     * group holding it rather than on anything the scan can see in front of it.
     * One pass with a stack of open groups settles all three at once, and
     * settles them for every comma in the body rather than re-deriving them per
     * parameter use:
     *
     * - **A separator inside an expression group** — a call's argument list, an
     *   array literal, a subscript. The comma ends one element, never the
     *   expression the group's own value feeds, so the scan resumes at the
     *   group's closing token: in
     *   `in_array($flag, [$value]) ? new Mailer() : new NullLogger()` it is the
     *   call's *result* the ternary selects on. Resuming at the closer rather
     *   than merely past the comma keeps the following elements out of the
     *   expression, so a ternary in a *sibling* argument stays that argument's.
     * - **A separator between the conditions of one `match` arm** — the arm's
     *   condition list is bounded by nothing but the arm's own `=>`, so the
     *   scan carries straight on at the same level and reaches that arrow.
     *   `match (true) { $legacy, $other => …, default => … }` branches on
     *   `$legacy` exactly as the one-condition spelling does.
     * - **The end of the expression** — a comma between two `match` arms ends
     *   the arm before it, and a comma at statement level ends a list
     *   (`echo $a, $b;`) no selector can follow. Neither is recorded, and an
     *   unrecorded comma ends the scan.
     *
     * A brace opens an expression group only when it opens a `match` arm list;
     * every other brace opens a *block*, and a comma in a block is at statement
     * level however deeply the block itself is nested.
     */
    private function buildCommaMap(File $phpcsFile, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $this->commaTargets = [];

        /** @var array<int, array{closer: int, arms: bool, block: bool, armBody: bool}> $groups */
        $groups = [];

        for ($pointer = $opener + 1; $pointer < $closer; $pointer++) {
            $depth = count($groups) - 1;

            if ($depth >= 0 && $pointer === $groups[$depth]['closer']) {
                array_pop($groups);

                continue;
            }

            $end = $this->openedGroupEnd($phpcsFile, $pointer);

            if ($end !== null) {
                $arms = $this->opensMatchArms($phpcsFile, $pointer);
                $groups[] = [
                    'closer' => $end,
                    'arms' => $arms,
                    'block' => !$arms && $tokens[$pointer]['code'] === T_OPEN_CURLY_BRACKET,
                    'armBody' => false,
                ];

                continue;
            }

            if ($depth < 0 || $groups[$depth]['block'] === true) {
                continue;
            }

            $code = $tokens[$pointer]['code'];

            if ($code === T_MATCH_ARROW && $groups[$depth]['arms']) {
                $groups[$depth]['armBody'] = true;

                continue;
            }

            if ($code !== T_COMMA) {
                continue;
            }

            if ($groups[$depth]['arms'] === false) {
                $this->commaTargets[$pointer] = $groups[$depth]['closer'];

                continue;
            }

            if ($groups[$depth]['armBody'] === true) {
                // The arm before this comma ends here; the arms after it are
                // their own expressions.
                $groups[$depth]['armBody'] = false;

                continue;
            }

            $this->commaTargets[$pointer] = $pointer;
        }
    }

    /**
     * Where the group *opening* at this token closes, or null when no group
     * opens here.
     *
     * Reads the closer from the opening token's own pairing, never from a
     * construct's `scope_closer` — see {@see self::GROUP_OPENERS}.
     */
    private function openedGroupEnd(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if (!in_array($token['code'], self::GROUP_OPENERS, true)) {
            return null;
        }

        $end = $token['parenthesis_closer'] ?? $token['bracket_closer'] ?? null;

        return $end !== null && $end > $pointer ? (int) $end : null;
    }

    /**
     * Whether this brace opens the arm list of a `match` — the one braced group
     * whose commas separate parts of an expression rather than statements.
     */
    private function opensMatchArms(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $tokens[$pointer]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_MATCH;
    }

    /**
     * Where the group opening at this token closes, or null when no group opens
     * here.
     *
     * Every kind of group counts, because every kind can hold a whole
     * sub-expression the scan must step over rather than read: a call's or a
     * grouping's parentheses, an array literal or a subscript's brackets, and a
     * braced group — the arm list of a `match` used as an operand, the body of
     * a closure or an anonymous class. The braced kind is why `scope_closer` is
     * consulted first: a `match` carries both a `parenthesis_closer` (its
     * subject) and a `scope_closer` (its arm list), and only the latter is the
     * end of the whole construct.
     */
    private function groupEnd(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        foreach (['scope_closer', 'parenthesis_closer', 'bracket_closer'] as $key) {
            if (isset($token[$key]) && $token[$key] > $pointer) {
                return (int) $token[$key];
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
     * Whether the condition this token owns guards a precondition rather than
     * selecting between initialization paths.
     *
     * A `throw` says "this call is not allowed", never "build it this way", so
     * a condition is a guard when the construct it belongs to is left with no
     * more than one way of constructing. That is the whole test, and it is
     * deliberately *not* asked of one branch alone: `if ($legacy) { throw … }
     * else { $this->value = $value; }` and `if ($legacy) { $this->value =
     * $value; } else { throw … }` are the same two branches in the opposite
     * order, and exempting the first while reporting the second would make the
     * exemption a fact about where the author put the throw. So every branch of
     * the construct is enumerated, and the condition is a guard when:
     *
     * - at least one branch throws — otherwise nothing is being rejected and
     *   the construct is not a guard at all, however few branches it has; and
     * - either the condition's *own* branch throws (the classic guard, whatever
     *   the other branches do with the call it lets through), or every branch
     *   but one throws (the mirror: one construction path survives, and the
     *   rest reject).
     *
     * A condition that governs the construct as a whole rather than one of its
     * branches — a `switch`/`match` subject, a ternary's condition — has no own
     * branch, so only the second leg can exempt it.
     *
     * An empty fall-through `case` has no body of its own to judge and is left
     * out of the count entirely.
     */
    private function isGuardClause(File $phpcsFile, int $branch): bool
    {
        [$construct, $own] = $this->constructOf($phpcsFile, $branch);

        if ($construct === null) {
            return false;
        }

        $branches = $this->branchStarts($phpcsFile, $construct);
        $throwing = 0;
        $surviving = 0;
        $ownThrows = false;

        foreach ($branches as $pointer => $start) {
            $throws = $this->firstStatementThrows($phpcsFile, $start);

            $throws ? $throwing++ : $surviving++;

            if ($pointer === $own) {
                $ownThrows = $throws;
            }
        }

        return $throwing > 0 && ($ownThrows || $surviving <= 1);
    }

    /**
     * The construct a branching token belongs to, and the branch of it the
     * token's condition owns — null when the condition governs the whole
     * construct rather than one branch.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function constructOf(File $phpcsFile, int $branch): array
    {
        $code = $phpcsFile->getTokens()[$branch]['code'];

        if ($code === T_IF || $code === T_ELSEIF) {
            return [$this->chainHead($phpcsFile, $branch), $branch];
        }

        if ($code === T_CASE || $code === T_DEFAULT) {
            return [$this->enclosingConstruct($phpcsFile, $branch, T_SWITCH), $branch];
        }

        if ($code === T_MATCH_ARROW) {
            return [$this->enclosingConstruct($phpcsFile, $branch, T_MATCH), $branch];
        }

        // A `switch`/`match` subject, or a ternary's condition: one condition
        // stands in front of every branch, so none of them is its own.
        return [$branch, null];
    }

    /**
     * Where each branch of this construct begins, keyed by the branch's own
     * token — the `if`/`elseif`/`else` of a chain, a `case`/`default` label, a
     * `match` arm's arrow, or a ternary's `?` and `:`.
     *
     * @return array<int, int>
     */
    private function branchStarts(File $phpcsFile, int $construct): array
    {
        $code = $phpcsFile->getTokens()[$construct]['code'];

        if ($code === T_SWITCH) {
            return $this->caseStarts($phpcsFile, $construct);
        }

        if ($code === T_MATCH) {
            return $this->armStarts($phpcsFile, $construct);
        }

        if ($code === T_INLINE_THEN) {
            return $this->ternarySides($phpcsFile, $construct);
        }

        return $this->chainStarts($phpcsFile, $construct);
    }

    /**
     * The `if` an `if`/`elseif`/`else` chain starts at, walking back from one of
     * its links.
     *
     * A link's predecessor is the closing brace of the branch in front of it, so
     * the walk hops from brace to owning keyword. A *brace-less* predecessor
     * (`if ($a) foo(); elseif ($b) …`) ends the walk instead of being followed:
     * the sub-chain from this link on is then judged on its own, which can only
     * ever exempt less than the whole chain would.
     */
    private function chainHead(File $phpcsFile, int $branch): int
    {
        $tokens = $phpcsFile->getTokens();
        $head = $branch;

        while (true) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $head - 1, null, true);

            // A spaced `else if` is a T_ELSE and a T_IF: the `if` owns the
            // condition, and the chain carries on in front of the `else`.
            if ($previous !== false && $tokens[$previous]['code'] === T_ELSE) {
                $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
            } elseif ($tokens[$head]['code'] !== T_ELSEIF) {
                return $head;
            }

            if ($previous === false || $tokens[$previous]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return $head;
            }

            $owner = $tokens[$previous]['scope_condition'] ?? null;

            if ($owner === null || !in_array($tokens[$owner]['code'], [T_IF, T_ELSEIF], true)) {
                return $head;
            }

            $head = (int) $owner;
        }
    }

    /**
     * Where each branch of the `if` chain starting at this token begins.
     *
     * @return array<int, int>
     */
    private function chainStarts(File $phpcsFile, int $head): array
    {
        $tokens = $phpcsFile->getTokens();
        $starts = [];
        $link = $head;

        while (true) {
            $starts[$link] = $this->branchStart($phpcsFile, $link);
            $end = $this->branchEnd($phpcsFile, $link);
            $next = $end === null ? false : $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

            if ($next === false || !in_array($tokens[$next]['code'], [T_ELSE, T_ELSEIF], true)) {
                return $starts;
            }

            $following = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
            $spacedElseIf = $tokens[$next]['code'] === T_ELSE
                && $following !== false
                && $tokens[$following]['code'] === T_IF;

            $link = $spacedElseIf ? (int) $following : (int) $next;

            if (isset($starts[$link])) {
                return $starts;
            }
        }
    }

    /**
     * Where the branch this keyword introduces begins: its own brace, or — with
     * no brace — the closing parenthesis of its condition, or the keyword
     * itself for an `else`.
     */
    private function branchStart(File $phpcsFile, int $branch): int
    {
        $token = $phpcsFile->getTokens()[$branch];

        return (int) ($token['scope_opener'] ?? $token['parenthesis_closer'] ?? $branch);
    }

    /**
     * Where the branch this keyword introduces ends, or null when its extent
     * cannot be read.
     */
    private function branchEnd(File $phpcsFile, int $branch): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$branch]['scope_closer'])) {
            return (int) $tokens[$branch]['scope_closer'];
        }

        $statement = $phpcsFile->findNext(Tokens::$emptyTokens, $this->branchStart($phpcsFile, $branch) + 1, null, true);

        return $statement === false ? null : (int) $phpcsFile->findEndOfStatement($statement);
    }

    /**
     * Where each non-empty `case`/`default` body of this switch begins.
     *
     * @return array<int, int>
     */
    private function caseStarts(File $phpcsFile, int $switch): array
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$switch]['scope_opener'], $tokens[$switch]['scope_closer'])) {
            return [];
        }

        $end = (int) $tokens[$switch]['scope_closer'];
        $starts = [];

        for ($pointer = $tokens[$switch]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if (!in_array($tokens[$pointer]['code'], [T_CASE, T_DEFAULT], true)) {
                continue;
            }

            if (!isset($tokens[$pointer]['scope_opener']) || !$this->isDirectBranchOf($phpcsFile, $pointer, $switch)) {
                continue;
            }

            $opener = (int) $tokens[$pointer]['scope_opener'];
            $first = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, $end, true);

            // An empty fall-through case has no body of its own to judge.
            if ($first === false || in_array($tokens[$first]['code'], [T_CASE, T_DEFAULT], true)) {
                continue;
            }

            $starts[$pointer] = $opener;
        }

        return $starts;
    }

    /**
     * Where each arm of this `match` begins.
     *
     * @return array<int, int>
     */
    private function armStarts(File $phpcsFile, int $match): array
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$match]['scope_opener'], $tokens[$match]['scope_closer'])) {
            return [];
        }

        $end = (int) $tokens[$match]['scope_closer'];
        $starts = [];

        for ($pointer = $tokens[$match]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if ($tokens[$pointer]['code'] !== T_MATCH_ARROW || !$this->isDirectBranchOf($phpcsFile, $pointer, $match)) {
                continue;
            }

            $starts[$pointer] = $pointer;
        }

        return $starts;
    }

    /**
     * The two sides of the ternary opening at this `?`, keyed by the token each
     * side follows.
     *
     * @return array<int, int>
     */
    private function ternarySides(File $phpcsFile, int $then): array
    {
        $tokens = $phpcsFile->getTokens();
        $sides = [$then => $then];
        $depth = 0;

        for ($pointer = $then + 1; $pointer < count($tokens); $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_SEMICOLON) {
                return $sides;
            }

            if ($code === T_INLINE_THEN) {
                $depth++;

                continue;
            }

            if ($code === T_INLINE_ELSE) {
                if ($depth === 0) {
                    $sides[$pointer] = $pointer;

                    return $sides;
                }

                $depth--;

                continue;
            }

            $pointer = $this->groupEnd($phpcsFile, $pointer) ?? $pointer;
        }

        return $sides;
    }

    /**
     * The innermost construct of this type holding the token, or null when
     * there is none.
     *
     * @param int|string $type
     */
    private function enclosingConstruct(File $phpcsFile, int $pointer, $type): ?int
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_reverse($tokens[$pointer]['conditions'] ?? [], true) as $owner => $code) {
            if ($code === $type) {
                return (int) $owner;
            }
        }

        return null;
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
     * Whether this `case` label or `match` arrow is a branch of the construct
     * at $owner itself, rather than of one nested inside it.
     *
     * Both walks below read a whole block looking for the branch tokens of one
     * construct, and a block can hold another construct of the same kind: a
     * `switch` inside a closure passed to a `throw`, a `match` inside the
     * message another `match` throws. The nested construct's branches are its
     * own — counting them as the outer construct's would let a non-throwing
     * nested branch report a guard that does throw on every branch of its own.
     * The innermost construct holding the token is the one it belongs to.
     */
    private function isDirectBranchOf(File $phpcsFile, int $pointer, int $owner): bool
    {
        $conditions = array_keys($phpcsFile->getTokens()[$pointer]['conditions'] ?? []);

        return end($conditions) === $owner;
    }

}
