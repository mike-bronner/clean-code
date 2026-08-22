<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
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
 * - **Re-bound names.** PHP scopes a variable to the whole function, so a
 *   `foreach` target, a `catch` variable, a `static` local, and a `global`
 *   import each replace what a name means from where they are written on. A
 *   parameter's name is read as the parameter until the first of those re-binds
 *   it, and as the new binding after it ({@see self::rebindingEnd()}). Only a
 *   name one of them writes *bare* re-binds: a dynamic target
 *   (`foreach ($rows as $row->{$mode})`, `global $$mode`) and a destructured
 *   element's key (`foreach ($rows as [$mode => $row])`) read the names
 *   spelling them and bind none of them ({@see self::boundNames()}). An
 *   assignment re-binds nothing either: `$mode = $mode ?? self::AUTO;`
 *   overwrites the parameter's value while the variable stays the parameter.
 * - **A name that is not PHP's own function.** Whether a predicate or an
 *   argument reader is the global function it reads as is
 *   {@see FunctionCalls::isGlobalFunctionCall()}'s answer — the package's one
 *   implementation of that test, which rules out a member, a declaration, an
 *   instantiation, an attribute, a qualified name, and a name a `use function`
 *   import redirects elsewhere. First-class callable syntax is ruled out beside
 *   it ({@see self::isFirstClassCallable()}): `func_get_args(...)` builds a
 *   Closure and reads no argument list where it is written.
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
     * Function declarations whose bodies are not constructor code. The walk
     * resumes at the closing brace of each, which skips the declaration whole,
     * parameter list included: a parameter's own default is a constant
     * expression, and a `use` clause only captures.
     *
     * An anonymous class is a nested declaration too, and is deliberately not
     * on this list: the arguments in `new class($legacy ? … : …) {}` are
     * evaluated by the constructor being walked, so only its *body* is skipped
     * — see {@see self::declarationSkip()}.
     *
     * @var array<int, int|string>
     */
    private const NESTED_DECLARATIONS = [T_FUNCTION, T_CLOSURE, T_FN];

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
     * Tokens after which a variable spells part of a dynamic target rather
     * than naming one — read by {@see self::bindsName()}.
     *
     * Each entry addresses something the variable's *value* selects: the
     * property of `$row->$name` and `$row->{$name}`, the element of
     * `$row[$name]`, the static property of `Row::$slot`, and the variable of
     * `$$name` and `${$name}`. The nullsafe operator writes nothing ("Can't
     * use nullsafe operator in write context"), and is listed beside its
     * sibling so a file PHP_CodeSniffer tokenizes but PHP rejects is read the
     * same way as one it accepts.
     *
     * A destructured element's own brackets are a `T_OPEN_SHORT_ARRAY`, which
     * PHP_CodeSniffer tells apart from the `T_OPEN_SQUARE_BRACKET` of a
     * subscript by what precedes it — so `[$first, $second]` binds both names
     * while `$row[$key]` binds neither.
     *
     * @var array<int, int|string>
     */
    private const INDIRECTION_PRECEDERS = [
        T_DOLLAR,
        T_DOUBLE_COLON,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_SQUARE_BRACKET,
    ];

    /**
     * Tokens before which a variable is the *container* a dynamic target
     * writes into rather than the name bound — the mirror of
     * {@see self::INDIRECTION_PRECEDERS}, read by the same method.
     *
     * `$row` in `$row->slot`, `$row?->slot`, `$row::$slot` and `$row[$key]`
     * keeps whatever it already meant; only the slot named after it is
     * written.
     *
     * @var array<int, int|string>
     */
    private const INDIRECTION_FOLLOWERS = [
        T_DOUBLE_COLON,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
        T_OPEN_SQUARE_BRACKET,
    ];

    /**
     * Tokens that open a nested group inside a binding construct's region,
     * and their closers — counted by {@see self::boundNames()} so a `=>` can
     * be read as the construct's own key separator or as a destructured
     * element's, whichever it is.
     *
     * The pair is the one CleanCode.Naming.ShortVariable reads a `foreach`
     * header's nesting with, widened by the braces of a dynamic member name.
     *
     * @var array<int, int|string>
     */
    private const NESTING_OPENERS = [
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
    ];

    /**
     * Their closers.
     *
     * @var array<int, int|string>
     */
    private const NESTING_CLOSERS = [
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_SQUARE_BRACKET,
    ];

    /**
     * Tokens that open a group holding a sub-expression: a call's or a
     * grouping's parentheses, an array literal's brackets, and the braces of a
     * `match` arm list or a block.
     *
     * A *subscript*'s brackets are absent, because this map is about commas
     * alone and PHP allows no comma between them — `$row[$a, $b]` is a parse
     * error. The forward scan reads subscripts through
     * {@see self::groupEnd()}, which does jump them.
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
    ];

    /**
     * The non-operator tokens after which a parenthesis groups an expression
     * rather than holding a call's arguments — read by
     * {@see self::opensGrouping()} on top of PHP_CodeSniffer's own operator,
     * boolean-operator, comparison, assignment and cast collections.
     *
     * Each entry is a position where an expression may start: an opening group
     * or a block's brace, the end of the statement or block before it, an
     * element or argument separator, a `case` label, a branch selector, or a
     * negation. A construct's own keyword is deliberately absent — `if (…)`,
     * `match (…)`, `isset(…)` and a declaration's parameter list all read their
     * parentheses rather than group an expression in them, so none of them
     * widens a subject.
     *
     * A ternary's `?` and `:` are `T_INLINE_THEN`/`T_INLINE_ELSE`, a `match`
     * arm's and an arrow function's selectors are `T_MATCH_ARROW`/`T_FN_ARROW`,
     * and an array `=>` is a `T_DOUBLE_ARROW` the assignment collection already
     * carries.
     *
     * `T_CLOSE_CURLY_BRACKET` is admitted for a block's brace alone, and is the
     * one entry membership does not settle: the same token also ends a dynamic
     * member name, whose next token opens that call's own argument list.
     * {@see self::closesBlock()} separates the two.
     *
     * @var array<int, int|string>
     */
    private const GROUPING_PRECEDERS = [
        T_BOOLEAN_NOT,
        T_CASE,
        T_CLOSE_CURLY_BRACKET,
        T_COLON,
        T_COMMA,
        T_FN_ARROW,
        T_INLINE_ELSE,
        T_INLINE_THEN,
        T_MATCH_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
        T_SEMICOLON,
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
     * What each branching construct's branches do, keyed by the construct's own
     * pointer.
     *
     * Every branch of a construct is enumerated to judge any one of its
     * conditions ({@see self::isGuardClause()}), and the answer is a fact about
     * the construct rather than about the condition that asked for it. Holding
     * it turns what would otherwise be one walk of the whole construct per
     * flagged token — quadratic on a `switch`, `match`, or `if` chain with many
     * branches, each testing a parameter — into one walk per construct.
     *
     * @var array<int, array{throws: array<int, bool>, throwing: int, surviving: int}>
     */
    private array $branchVerdicts = [];

    /**
     * The `if` each link of an `if`/`elseif`/`else` chain belongs to, keyed by
     * the link's own pointer.
     *
     * The walk back to the head is deterministic and reads nothing in front of
     * the link it starts at, so every link it steps on has the same head as the
     * walk that reached it. Recording all of them turns one walk of the whole
     * chain per link into one walk per chain — the same amortization
     * {@see self::remember()} applies to the forward scan.
     *
     * @var array<int, int>
     */
    private array $chainHeadCache = [];

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

        // Every map below describes this constructor's body alone, and the
        // scan's answers depend on where that body ends, so none of them
        // survives into the next constructor.
        $this->selectorCache = [];
        $this->branchVerdicts = [];
        $this->chainHeadCache = [];
        $this->buildCommaMap($phpcsFile, $tokens[$stackPtr]['scope_opener'], $closer);

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            $skip = $this->declarationSkip($phpcsFile, $pointer);

            if ($skip !== null) {
                $pointer = $skip;

                continue;
            }

            $rebinding = $this->rebindingEnd($phpcsFile, $pointer, $closer);

            if ($rebinding !== null) {
                $parameters = array_diff_key($parameters, $this->boundNames($phpcsFile, $pointer, $rebinding));
                $pointer = $rebinding;

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
     * Where the walk resumes past a nested declaration reached at this token,
     * or null when the token opens none.
     *
     * A function declaration is skipped from its keyword, so nothing between
     * the keyword and the closing brace is read. An anonymous class is skipped
     * from its *brace* instead: its body is no more constructor code than a
     * function's is, but the arguments in front of that brace are the
     * constructor's own expressions, evaluated where they are written.
     */
    private function declarationSkip(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if (!isset($token['scope_closer'])) {
            return null;
        }

        $owner = $token['scope_condition'] ?? null;
        $anonymousClassBody = $token['code'] === T_OPEN_CURLY_BRACKET
            && $owner !== null
            && $phpcsFile->getTokens()[$owner]['code'] === T_ANON_CLASS;

        return in_array($token['code'], self::NESTED_DECLARATIONS, true) || $anonymousClassBody
            ? (int) $token['scope_closer']
            : null;
    }

    /**
     * Where the re-binding introduced at this token ends, or null when the
     * token introduces none.
     *
     * PHP scopes a variable to the whole function, so a name a body re-binds
     * stops being the parameter from that point on — including after the
     * construct that re-bound it, since none of them opens a scope of its own.
     * Four constructs bind a name inside a function body, and this is all of
     * them ("Variable scope" in the PHP manual, read against the constructs a
     * constructor body can hold): a `foreach` target, a `catch` variable, a
     * `static` local, and a `global` import. A closure's parameters and `use`
     * list, an arrow function's parameters and a named function's are the fifth
     * and are already out of reach — {@see self::declarationSkip()} jumps every
     * nested declaration whole, at its keyword.
     *
     * An *assignment* is deliberately not one of them. `$mode = $mode ?? self::AUTO;`
     * overwrites the parameter's value while the variable stays the parameter,
     * and branching on it afterwards is still branching on the mode the caller
     * supplied — reading a normalized flag as a different variable would silence
     * the commonest spelling of the very thing this sniff reports.
     *
     * The region each construct binds in holds its targets — a `foreach`'s,
     * a `catch`'s exception variable, the constant expression a `static` local
     * is initialized to — and the walk resumes past the whole of it rather
     * than reading it. Which of the names written there the construct actually
     * binds is {@see self::boundNames()}'s answer, since a target can be
     * dynamic and spell itself with names it only reads.
     *
     * A dynamic target's own subscript may in principle carry a branch
     * (`foreach ($rows as $row[$mode ? 'a' : 'b'])`), and stepping past the
     * region leaves it unread. That costs a missed warning on a spelling
     * nobody writes rather than a wrong one on a common spelling — the same
     * trade this file makes for a named-argument predicate call.
     */
    private function rebindingEnd(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$pointer]['code'];

        if ($code === T_AS) {
            return $this->foreachHeaderEnd($phpcsFile, $pointer);
        }

        if ($code === T_CATCH) {
            return isset($tokens[$pointer]['parenthesis_closer'])
                ? (int) $tokens[$pointer]['parenthesis_closer']
                : null;
        }

        if ($code !== T_GLOBAL && !($code === T_STATIC && $this->declaresLocals($phpcsFile, $pointer))) {
            return null;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $pointer + 1, $closer);

        return $semicolon === false ? null : (int) $semicolon;
    }

    /**
     * Where the `foreach` header holding this token ends, or null when no
     * `foreach` header holds it.
     *
     * The enclosing pairs are read inward-out and only a pair PHP_CodeSniffer
     * attributes to a `T_FOREACH` answers, exactly as {@see self::branchOwner()}
     * reads a condition's owner. No such pair means the `as` belongs to
     * something that is not a loop — a trait adaptation (`use A as B;`), which
     * a class body holds rather than a constructor's, or an aliasing `use`
     * statement at file scope.
     *
     * A `foreach` is a statement, so its header can be nested in no other
     * parenthesis pair of the same declaration, and the walk jumps every nested
     * declaration whole ({@see self::declarationSkip()}). Reading the pairs
     * inward-out is therefore consistency with the rest of the file rather than
     * a case any fixture can tell apart — recorded as observed, not asserted.
     */
    private function foreachHeaderEnd(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        foreach (array_reverse($openers) as $opener) {
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if ($owner !== null && $tokens[$owner]['code'] === T_FOREACH) {
                return (int) $tokens[$owner]['parenthesis_closer'];
            }
        }

        return null;
    }

    /**
     * Whether the `static` at this pointer declares local variables —
     * `static $seen = [];` — rather than naming the late-static-bound class
     * (`static::make()`, `new static()`) or marking a closure
     * (`static function () { … }`, `static fn () => …`).
     *
     * The declaration is the one spelling whose next token is a variable.
     */
    private function declaresLocals(File $phpcsFile, int $pointer): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return $next !== false && $phpcsFile->getTokens()[$next]['code'] === T_VARIABLE;
    }

    /**
     * The names bound between $from and $to, as a set keyed by name.
     *
     * A construct's region holds its targets, and only a target written bare
     * binds the name it is spelled with. Every other variable in the region is
     * *read* there: the ones spelling a dynamic target
     * (`foreach ($rows as $row->{$mode})`, `global $$mode`) select where the
     * write lands, and the key of a destructured element
     * (`foreach ($rows as [$mode => $row])`) addresses an element rather than
     * receiving one. Reading either as a binding drops a parameter the
     * constructor still branches on further down — the very report this sniff
     * exists to make — so each is left in the map.
     *
     * The nesting count separates the two spellings of `=>`: a key at the
     * construct's own nesting is the `foreach`'s, and binds
     * (`foreach ($rows as $key => $row)`), while one inside a destructuring
     * group belongs to the element being addressed, and does not.
     *
     * @return array<string, true>
     */
    private function boundNames(File $phpcsFile, int $from, int $to): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $depth = 0;

        for ($pointer = $from + 1; $pointer < $to; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::NESTING_OPENERS, true)) {
                $depth++;

                continue;
            }

            if (in_array($code, self::NESTING_CLOSERS, true)) {
                $depth--;

                continue;
            }

            if ($code === T_VARIABLE && $this->bindsName($phpcsFile, $pointer, $depth)) {
                $names[$tokens[$pointer]['content']] = true;
            }
        }

        return $names;
    }

    /**
     * Whether the variable at this pointer is a name its construct binds,
     * rather than one read to reach a target.
     *
     * A bare name is dereferenced from neither side: nothing in front of it
     * makes it the selector of a dynamic target
     * ({@see self::INDIRECTION_PRECEDERS}) and nothing behind it makes it the
     * container one is written into ({@see self::INDIRECTION_FOLLOWERS}). A
     * `&` in front binds by reference and is not indirection, so a
     * by-reference `foreach` value binds like any other.
     *
     * $depth is the variable's nesting inside the region, and settles the one
     * token that means either thing — a `=>` behind a variable is the
     * construct's key separator at depth 0 and a destructured element's key
     * anywhere deeper.
     */
    private function bindsName(File $phpcsFile, int $pointer, int $depth): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if ($before !== false && in_array($tokens[$before]['code'], self::INDIRECTION_PRECEDERS, true)) {
            return false;
        }

        if ($after === false) {
            return true;
        }

        if (in_array($tokens[$after]['code'], self::INDIRECTION_FOLLOWERS, true)) {
            return false;
        }

        return $tokens[$after]['code'] !== T_DOUBLE_ARROW || $depth === 0;
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
     * "Direct argument" is exact twice over: the parameter must be the whole of
     * the *first* argument, the only one any of these predicates takes as its
     * subject — see {@see self::isBareFirstArgument()} — and nothing may stand
     * between it and that argument's position, so `is_string(trim($value))`
     * tests a derived value rather than the parameter. The two-argument
     * spellings `is_a($value, $expectedClass)` and
     * `is_subclass_of($value, $expectedClass)` test `$value` alone — the class
     * name they compare it against is a value the call reads, never a parameter
     * whose own type is being switched on.
     *
     * A redundant grouping parenthesis is not a decoration: `is_string(($value))`
     * and `($value) instanceof Mailer` test the same parameter the unwrapped
     * spellings do. So the enclosing parentheses are read from the inside out,
     * and a pair grouping nothing but the subject widens the subject to itself
     * rather than ending the read. Both spellings of a type test are asked of
     * every width the subject reaches, because a grouping parenthesis stands in
     * front of either one: the `instanceof` behind the widened subject, and the
     * predicate call around it.
     *
     * A pair holding anything besides the subject — an operand beside it — ends
     * the read, and so does a pair that is not a grouping at all
     * ({@see self::opensGrouping()}). Between them they keep
     * `is_string(trim($value))`, `is_string($value . $suffix)` and
     * `$this->resolve($value) instanceof Mailer` silent: each tests a derived
     * value rather than the parameter.
     */
    private function isTypeTested(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $start = $pointer;
        $end = $pointer;

        if ($this->isInstanceofSubject($phpcsFile, $end)) {
            return true;
        }

        foreach (array_reverse($tokens[$pointer]['nested_parenthesis'] ?? [], true) as $opener => $closer) {
            if (
                $this->isTypePredicate($phpcsFile, (int) $opener)
                && $this->isBareFirstArgument($phpcsFile, (int) $opener, (int) $closer, $start, $end)
            ) {
                return true;
            }

            if (
                !$this->opensGrouping($phpcsFile, (int) $opener)
                || !$this->wrapsNothingElse($phpcsFile, (int) $opener, (int) $closer, $start, $end)
            ) {
                return false;
            }

            $start = (int) $opener;
            $end = (int) $closer;

            if ($this->isInstanceofSubject($phpcsFile, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the span ending at $end is the left operand of an `instanceof`.
     */
    private function isInstanceofSubject(File $phpcsFile, int $end): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

        return $next !== false && $phpcsFile->getTokens()[$next]['code'] === T_INSTANCEOF;
    }

    /**
     * Whether the parenthesis at $opener groups an expression, rather than
     * being the argument list of a call or the parentheses of a construct.
     *
     * The distinction is not decoration: `($value)` evaluates to the parameter,
     * while `resolve($value)` evaluates to whatever the call returns, and the
     * two are told apart by the token in front of the parenthesis alone — PHP
     * spells them identically otherwise. So a parenthesis widens the subject
     * only where an expression may *start*: after an operator, an assignment, a
     * cast, an opening group, a statement or element separator, a branch
     * selector, or a negation.
     *
     * The test is deliberately positive, and every operator family is taken
     * whole from PHP_CodeSniffer's own maintained collections rather than
     * re-listed here. A spelling missing from it therefore ends the read and
     * costs a missed warning, never a wrong one — the same trade this sniff
     * already makes on a named-argument predicate call.
     *
     * One admitted spelling is not positive on its own, so it is asked a second
     * question: {@see self::closesBlock()}.
     */
    private function opensGrouping(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        if ($before === false || !isset($this->groupingPreceders()[$tokens[$before]['code']])) {
            return false;
        }

        return $tokens[$before]['code'] !== T_CLOSE_CURLY_BRACKET
            || $this->closesBlock($phpcsFile, $before);
    }

    /**
     * Whether the `}` at $pointer closes a block, rather than a curly-brace
     * name segment.
     *
     * `T_CLOSE_CURLY_BRACKET` is the one admitted preceder PHP spells two ways.
     * It ends an ordinary block — and the statement with it, so an expression
     * may start after it — but it also ends a dynamic member name, where the
     * very next token is *that call's own* argument list: `$this->{$name}(…)`,
     * `self::{$name}(…)`, `$obj?->{$name}(…)`. Reading the second as a grouping
     * reports the parameter handed to the call as the subject of a test on what
     * the call returns.
     *
     * PHP_CodeSniffer tells them apart on the closer itself: every block-bearing
     * construct — `if`/`elseif`/`else`, `try`/`catch`/`finally`, `do`, `switch`,
     * `for`/`foreach`/`while`, a function, closure, class, anonymous class,
     * trait, interface, enum, `match` and a `namespace` block — gives its
     * closing brace a `scope_condition`, and a name segment's brace carries
     * none. A bare `{ … }` block carries none either, so it ends the read: the
     * same positive test the rest of this lookback makes, costing a missed
     * warning rather than a wrong one.
     */
    private function closesBlock(File $phpcsFile, int $pointer): bool
    {
        return isset($phpcsFile->getTokens()[$pointer]['scope_condition']);
    }

    /**
     * The tokens after which a parenthesis groups an expression, keyed by token
     * code. Held across calls because the union is the same for every file.
     *
     * @return array<int|string, int|string>
     */
    private function groupingPreceders(): array
    {
        static $preceders = null;

        return $preceders ??= Tokens::$operators
            + Tokens::$booleanOperators
            + Tokens::$comparisonTokens
            + Tokens::$assignmentTokens
            + Tokens::$castTokens
            + array_combine(self::GROUPING_PRECEDERS, self::GROUPING_PRECEDERS);
    }

    /**
     * Whether the parenthesis at $opener is the call parenthesis of one of the
     * type predicates, called as PHP's own global function.
     *
     * Resolving the name is {@see FunctionCalls::isGlobalFunctionCall()}'s job
     * rather than this sniff's, exactly as it is for the argument readers —
     * see {@see self::isArgumentReader()}.
     */
    private function isTypePredicate(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        return $callee !== false
            && $tokens[$callee]['code'] === T_STRING
            && in_array(strtolower($tokens[$callee]['content']), self::TYPE_PREDICATES, true)
            && FunctionCalls::isGlobalFunctionCall($phpcsFile, $callee);
    }

    /**
     * Whether the parenthesis pair at $opener/$closer holds the span from
     * $start to $end and nothing besides — a redundant grouping of the subject.
     *
     * Comments are not content, so a subject wrapped in them is still the whole
     * of the pair, exactly as it is still the bare first argument of a call.
     */
    private function wrapsNothingElse(File $phpcsFile, int $opener, int $closer, int $start, int $end): bool
    {
        return $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true) === $start
            && $phpcsFile->findPrevious(Tokens::$emptyTokens, $closer - 1, null, true) === $end;
    }

    /**
     * Whether the span from $start to $end is the *whole* of the first argument
     * of the call opening at $opener — the bare parameter itself, undecorated
     * but for any grouping parentheses already read around it.
     *
     * Totality is the point, not mere precedence. Confirming that no argument
     * separator *precedes* the span says nothing about what the call actually
     * tests: `is_string($obj->prop)`, `is_string($items[$key])` and
     * `is_a(class: $class, object: $source)` all put a parameter in the first
     * argument's span without that parameter being the subject. So the span
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
    private function isBareFirstArgument(File $phpcsFile, int $opener, int $closer, int $start, int $end): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $start - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

        return $before === $opener
            && $after !== false
            && ($after === $closer || $phpcsFile->getTokens()[$after]['code'] === T_COMMA);
    }

    /**
     * Whether a T_STRING names one of the argument-list readers, actually
     * called as PHP's own global function.
     *
     * Whether a name resolves to the global function is
     * {@see FunctionCalls::isGlobalFunctionCall()}'s answer rather than this
     * sniff's. That helper is the repo's one implementation of the test, and
     * every sniff that flags a global function call routes through it
     * (CONTRIBUTING.md, "The shared helpers") so a shape fixed there is fixed
     * here: it tells a real call apart from a member (`->`, `?->`, `::`), a
     * declaration, an instantiation, an attribute name, a name qualified into
     * another namespace, and a bare name a `use function` import redirects
     * elsewhere.
     *
     * The helper stops at the opening parenthesis, and a first-class callable
     * is spelled with the same tokens up to it — see
     * {@see self::isFirstClassCallable()}.
     */
    private function isArgumentReader(File $phpcsFile, int $pointer): bool
    {
        if (!in_array(strtolower($phpcsFile->getTokens()[$pointer]['content']), self::ARGUMENT_READERS, true)) {
            return false;
        }

        if (!FunctionCalls::isGlobalFunctionCall($phpcsFile, $pointer)) {
            return false;
        }

        // The helper has already established that this is the call's own
        // opening parenthesis.
        $opener = (int) $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return !$this->isFirstClassCallable($phpcsFile, $opener);
    }

    /**
     * Whether the parentheses opening at $opener spell PHP 8.1 first-class
     * callable syntax — `func_get_args(...)` — rather than a call.
     *
     * The two are the same tokens up to the opening parenthesis, so a test that
     * stops there reads `f(...)` as a call to `f`. It is not one: it builds a
     * Closure and calls nothing, so the constructor's own argument list is
     * never read where the reference is written, and no mode switch has
     * happened there. The idiom is the sibling UnusedFormalParameterSniff's and
     * DisallowCountInLoopExpressionSniff's, ported rather than re-derived.
     *
     * The literal `...` has to be the whole list. A spread of a real argument —
     * `f(...$arguments)` — puts a variable after the ellipsis instead of the
     * closer, and that is a call like any other, so the token after the
     * ellipsis is checked for the closing parenthesis rather than the ellipsis
     * being taken alone.
     *
     * Only the argument readers ask this. A first-class callable to a type
     * predicate — `is_string(...)` — holds no argument for a subject test to
     * read, so {@see self::isBareFirstArgument()} rejects it whatever this
     * method would answer.
     */
    private function isFirstClassCallable(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true);

        if ($ellipsis === false || $tokens[$ellipsis]['code'] !== T_ELLIPSIS) {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $ellipsis + 1, null, true);

        return $after !== false && $tokens[$after]['code'] === T_CLOSE_PARENTHESIS;
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

        $verdicts = $this->branchVerdicts($phpcsFile, $construct);
        $ownThrows = $own !== null && ($verdicts['throws'][$own] ?? false);

        return $verdicts['throwing'] > 0 && ($ownThrows || $verdicts['surviving'] <= 1);
    }

    /**
     * Whether each branch of this construct throws, with the throwing and
     * surviving branches counted — read once per construct and held.
     *
     * @return array{throws: array<int, bool>, throwing: int, surviving: int}
     */
    private function branchVerdicts(File $phpcsFile, int $construct): array
    {
        if (isset($this->branchVerdicts[$construct])) {
            return $this->branchVerdicts[$construct];
        }

        $verdicts = ['throws' => [], 'throwing' => 0, 'surviving' => 0];

        foreach ($this->branchStarts($phpcsFile, $construct) as $pointer => $start) {
            $throws = $this->firstStatementThrows($phpcsFile, $start);
            $verdicts['throws'][$pointer] = $throws;

            $throws ? $verdicts['throwing']++ : $verdicts['surviving']++;
        }

        return $this->branchVerdicts[$construct] = $verdicts;
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
        $visited = [];

        while (true) {
            if (isset($this->chainHeadCache[$head])) {
                return $this->rememberChainHead($visited, $this->chainHeadCache[$head]);
            }

            $visited[] = $head;
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $head - 1, null, true);

            // A spaced `else if` is a T_ELSE and a T_IF: the `if` owns the
            // condition, and the chain carries on in front of the `else`.
            if ($previous !== false && $tokens[$previous]['code'] === T_ELSE) {
                $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
            } elseif ($tokens[$head]['code'] !== T_ELSEIF) {
                return $this->rememberChainHead($visited, $head);
            }

            if ($previous === false || $tokens[$previous]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return $this->rememberChainHead($visited, $head);
            }

            $owner = $tokens[$previous]['scope_condition'] ?? null;

            if ($owner === null || !in_array($tokens[$owner]['code'], [T_IF, T_ELSEIF], true)) {
                return $this->rememberChainHead($visited, $head);
            }

            $head = (int) $owner;
        }
    }

    /**
     * Records one walk's head against every link that walk stepped on, and
     * hands the head back.
     *
     * @param array<int, int> $visited
     */
    private function rememberChainHead(array $visited, int $head): int
    {
        foreach ($visited as $link) {
            $this->chainHeadCache[$link] = $head;
        }

        return $head;
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

        $start = $this->branchStart($phpcsFile, $branch);
        $statement = $phpcsFile->findNext(Tokens::$emptyTokens, $start + 1, null, true);

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
