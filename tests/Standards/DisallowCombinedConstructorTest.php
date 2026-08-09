<?php

/**
 * Tests the custom CleanCode.Constructors.DisallowCombinedConstructor sniff
 * (Primary + Named Constructors, #34 — the combined-constructor slice scoped on
 * #193). Fixtures live in tests/fixtures/DisallowCombinedConstructorSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors:
 * branching in a constructor is a design smell, not always a defect, and
 * splitting one into named constructors rewrites the class's construction API
 * and every call site. So there is no autofixed fixture, and the tests below
 * prove no reported violation is fixable.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 *
 * Every guard in the sniff was mutation-checked against these fixtures, and the
 * result of each mutation is recorded here rather than assumed — each number
 * below was produced by disabling that guard and re-running the fixtures, not
 * derived. Counts are warnings per fixture, against the baseline
 * passing 0 / failing 4 / shapes 31; a fixture whose count the mutation leaves
 * unchanged is omitted from its line.
 *
 * Scope of the walk:
 *
 *   - drop the `__construct` name check — passing 13 (the named constructor,
 *     the ordinary method, the `__constructor()` lookalike and the file-scope
 *     function all start reporting)
 *   - drop the class-like-scope gate — passing 3 (the file-scope
 *     `function __construct()` starts reporting)
 *   - drop the nested-declaration skip — passing 10 (the nested named function,
 *     the closure, the arrow function and the anonymous class's method all
 *     start reporting)
 *   - drop `T_FUNCTION` alone from the nested-declaration list — passing 3
 *     (only the nested named function starts reporting, which is what pins
 *     that entry separately from its three siblings)
 *
 * What counts as a signal:
 *
 *   - drop the "flag or type test" requirement, reporting any parameter in a
 *     condition — passing 16, shapes 33
 *   - drop the `bool`-type leg of the flag test — failing 3, shapes 17
 *   - drop the `true`/`false`-default leg of the flag test — shapes 29 (both
 *     DefaultedModeFlags parameters stop reporting)
 *   - drop the leading-`?` strip in type normalization — shapes 30 (`?bool`
 *     stops being a flag)
 *   - drop the explicit `null` union member from type normalization —
 *     shapes 30 (`bool|null` stops being a flag)
 *   - accept any union *containing* `bool` rather than exactly `bool` —
 *     passing 1 (`bool|string` starts reporting)
 *   - drop the variadic exclusion — passing 1 (`bool ...$flags`)
 *   - drop `instanceof` detection — shapes 29
 *   - drop the member/static/`new` qualifier check on a name — passing 4
 *     (`$request->func_num_args()`, `Reflector::func_get_args()`, and the two
 *     comment-separated member calls)
 *   - take the outermost enclosing parenthesis for a predicate call instead of
 *     the innermost — failing 3, shapes 26 (every predicate applied directly to
 *     a parameter stops being recognised)
 *   - scan forward from the parameter rather than from the predicate call's
 *     closing parenthesis — shapes 30 (the `is_subclass_of()` ternary stops
 *     reporting: the comma between the two arguments ends the scan first)
 *
 * Argument totality — that the parameter is the *whole* first argument, each
 * half of the test pinned on its own:
 *
 *   - drop the bare-first-argument check entirely — passing 5, shapes 33
 *   - drop only its "the opening parenthesis precedes it" clause — passing 2,
 *     shapes 33 (the two named arguments start reporting)
 *   - drop only its "a separator or the closer follows it" clause — passing 2
 *     (the property read and the subscripted array start reporting)
 *
 * Comment tolerance — every adjacency test skips `Tokens::$emptyTokens`, and
 * reverting any one of the five to `T_WHITESPACE` alone flips a verdict:
 *
 *   - the `instanceof` lookahead — shapes 30 (false negative)
 *   - the predicate-callee lookback — shapes 30 (false negative)
 *   - the argument-reader lookahead — shapes 30 (false negative)
 *   - the name-qualifier lookback — passing 2 (false positives: the member
 *     calls named `is_a` and `func_num_args` are read as the global functions)
 *   - the elvis lookahead — passing 1 (false positive: the elvis default is
 *     read as a branch)
 *
 * Guard clauses:
 *
 *   - drop the exemption for mode flags and type tests — passing 14 (every
 *     guard shape carrying those two signals starts reporting)
 *   - drop the exemption for the argument readers — passing 3 (the braced,
 *     brace-less and ternary guards of GuardedArgumentCount)
 *   - stop treating an all-throwing `switch` as a guard — passing 1; treat
 *     every `switch` as one regardless of its cases — shapes 29
 *   - stop treating an all-throwing `match` as a guard — passing 1; treat
 *     every `match` as one regardless of its arms — shapes 30
 *   - drop the brace-less fallback to the condition's closing parenthesis —
 *     passing 3 (both brace-less guards and the brace-less argument-reader
 *     guard start reporting)
 *
 * Where an expression ends:
 *
 *   - report `?:` as a branch — passing 2 (the elvis default over a mode flag,
 *     and its comment-separated spelling)
 *   - drop `case`-label detection — shapes 30 (`case is_iterable($extra):`)
 *   - drop the match-arm selector — shapes 29 (both arm-condition signals)
 *   - drop the parenthesised-condition scan — passing 2, failing 2, shapes 15
 *   - drop the bracket jump, or any one of the five expression terminators
 *     (`;`, `,`, array `=>`, `{`, `:`) — passing 1 each, one boundary per
 *     statement of the ExpressionEnds fixture
 *
 * The bodiless-declaration guard is the one that does not redden an assertion,
 * and is stated as observed rather than assumed: removing it makes PHPCS abort
 * the file with an `Internal.Exception` error ("Undefined array key
 * scope_closer"), so passing.php reports one error and no warnings, and this
 * test file's compliant-fixture case is marked risky by a PHP warning while the
 * suite still exits 0. The abstract, interface and promotion-only constructors
 * in passing.php therefore pin that the sniff stays silent and does not fall
 * over on a declaration with no body, matching how
 * tests/Standards/DisallowConstructorInstantiationTest pins its own.
 */

declare(strict_types=1);

const COMBINED_CONSTRUCTOR = 'CleanCode.Constructors.DisallowCombinedConstructor';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMBINED_CONSTRUCTOR);
});

/**
 * passing.php carries the compliant form of the construct the sniff registers
 * on — one unconditional primary constructor with a named constructor per
 * construction scenario — plus every near-miss shape the sniff must stay silent
 * on: guard clauses in each branching form and for each of the three signals,
 * coalesce defaults over a mode flag,
 * a non-boolean parameter in a condition, a predicate applied to a derived
 * value — through a property read, a subscript, and a named argument as well as
 * a nested call — a `bool|string` union that is not a flag, all three signals in
 * a named constructor, an ordinary method, a nested named function, a closure,
 * an arrow function and an anonymous class, member calls named like a predicate
 * and like the argument readers with a comment splitting the object operator,
 * static calls named like the argument readers, a variadic flag, every bodiless
 * constructor shape, and a statement per expression boundary the forward scan
 * must respect.
 * Dropping any one of the sniff's guards reddens this test.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One class per signal in failing.php, so each violation code is provoked
 * independently and a code that fell silent could not be masked by its
 * siblings. Every report lands on the parameter being switched on, or on the
 * argument reader's own name — never on the branching keyword.
 */
it('warns once per mode signal, under the signal\'s own code', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 51, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 67, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 69, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a constructor the sniff has no safe rewrite for. getFixableCount() is
 * used rather than violationFixableFlags(), which reads getErrors() only and so
 * would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a token walk that only ever handled the one
 * spelling it was written against. A signal per branching form, per declaration
 * form, and per placement of the argument readers:
 *
 *   33, 35, 37 — `if`, `elseif`, and a *spaced* `else if`. The last is why the
 *                sniff keys on the `T_IF` that owns the parentheses rather than
 *                on the `T_ELSE` in front of it
 *   41         — a `switch` subject
 *   49         — a `match` subject
 *   55         — a `match` arm condition, where the test sits in the branch
 *                rather than in the head
 *   59         — a ternary condition
 *   61         — a brace-less `if`, which carries no scope opener at all
 *   63         — the alternative syntax, whose scope opener is the `:`
 *   77         — `instanceof`
 *   83, 85     — `is_array()` and `is_callable()` in an `if` and an `elseif`
 *   89         — `gettype()` compared to a type name
 *   95         — a predicate in a ternary condition
 *   98         — a predicate in a `match` arm condition
 *  103         — a predicate in a `switch` case label, the `switch` counterpart
 *                of line 98
 *  121,122,125 — the argument readers, which need no branch to be a signal: in
 *                a condition, in a nested `if` body, and spelled in upper case
 *                in a `foreach` subject, since PHP resolves function names
 *                case-insensitively
 *  141         — a constructor declared in a trait, on a *promoted* parameter,
 *                so promotion cannot hide a flag from the body scan
 *  149         — `__CONSTRUCT`, since PHP method names are case-insensitive
 *  160         — a constructor of an anonymous class
 *  176, 178    — a flag that qualifies by its `true`/`false` default rather than
 *                by a `bool` type: one untyped, one typed `mixed`
 *  194, 200    — `is_a()` and `is_subclass_of()`, the two predicates that take a
 *                second argument. Only the subject reports; the class name it is
 *                compared against never does, in either the `if` or the ternary
 *  216,222,228 — a comment standing where the walk needs adjacency: before an
 *                `instanceof`, before a predicate's call parentheses, and before
 *                an argument reader's parentheses. Each still fires, because
 *                every adjacency test skips comments as well as whitespace
 *  245, 247    — `?bool` and `bool|null`, the two spellings that normalize to
 *                plain `bool`, neither carrying a `true`/`false` default that
 *                could qualify it by the other leg instead
 */
it('warns on every branching, declaration, and argument-reader shape', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 35, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 37, 'column' => 20, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 41, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 49, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 55, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 59, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 61, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 63, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 77, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 83, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 85, 'column' => 31, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 89, 'column' => 21, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 95, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 98, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 103, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 121, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 122, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 125, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 141, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 149, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 160, 'column' => 36, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 176, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 178, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 194, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 200, 'column' => 39, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 216, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 222, 'column' => 53, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 228, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 245, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 247, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});
