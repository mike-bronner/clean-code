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
 * result of each mutation is recorded here rather than assumed. Counts are
 * warnings per fixture, against the baseline passing 0 / failing 4 / shapes 22:
 *
 *   - drop the `__construct` name check — passing 13 (the named constructor,
 *     the ordinary method, the `__constructor()` lookalike and the file-scope
 *     function all start reporting)
 *   - drop the class-like-scope gate — passing 3 (the file-scope
 *     `function __construct()` starts reporting)
 *   - drop the nested-declaration skip — passing 7 (the closure, the arrow
 *     function and the anonymous class's method start reporting)
 *   - drop the guard-clause exemption — passing 14 (every guard shape starts
 *     reporting)
 *   - report `?:` as a branch — passing 1 (the elvis default over a mode flag)
 *   - drop the "flag or type test" requirement, reporting any parameter in a
 *     condition — passing 10 (`$retries > 3`, `$mode`, and the loop headers)
 *   - take the outermost enclosing parenthesis for a predicate call instead of
 *     the innermost — failing 3, shapes 19 (every predicate applied directly to
 *     a parameter stops being recognised)
 *   - drop the member/static/`new` qualifier check on a name — passing 2
 *     (`$request->func_num_args()` and `Reflector::func_get_args()`)
 *   - drop the brace-less fallback to the condition's closing parenthesis —
 *     passing 2 (both brace-less guards start reporting)
 *   - stop requiring every `switch` case to throw — passing 1
 *   - stop requiring every `match` arm to throw — passing 1
 *   - drop `case`-label detection — shapes 21 (`case is_iterable($extra):`)
 *   - drop the match-arm selector — shapes 20 (both arm-condition signals)
 *   - drop `instanceof` detection — shapes 21
 *   - drop the parenthesised-condition scan — failing 2, shapes 11
 *   - drop the variadic exclusion — passing 1 (`bool ...$flags`)
 *   - drop the bracket jump, or any one of the five expression terminators
 *     (`;`, `,`, array `=>`, `{`, `:`) — passing 1 each, one boundary per
 *     statement of the ExpressionEnds fixture
 *
 * The bodyless-declaration guard is the one exception, and is stated plainly:
 * removing it changes no report — the walk simply never starts — and only
 * raises a PHP undefined-key warning, which this suite does not fail on. The
 * abstract, interface and promotion-only constructors in passing.php therefore
 * pin that the sniff stays silent and does not fall over on a declaration with
 * no body, matching how tests/Standards/DisallowConstructorInstantiationTest
 * pins its own; it is not a red-on-mutation assertion.
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
 * on: guard clauses in each branching form, coalesce defaults over a mode flag,
 * a non-boolean parameter in a condition, a predicate applied to a derived
 * value, all three signals in a named constructor, an ordinary method, a
 * closure, an arrow function and an anonymous class, member and static calls
 * named like the argument readers, a variadic flag, every bodiless constructor
 * shape, and a statement per expression boundary the forward scan must respect.
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
 *   32, 34, 36 — `if`, `elseif`, and a *spaced* `else if`. The last is why the
 *                sniff keys on the `T_IF` that owns the parentheses rather than
 *                on the `T_ELSE` in front of it
 *   40         — a `switch` subject
 *   48         — a `match` subject
 *   54         — a `match` arm condition, where the test sits in the branch
 *                rather than in the head
 *   58         — a ternary condition
 *   60         — a brace-less `if`, which carries no scope opener at all
 *   62         — the alternative syntax, whose scope opener is the `:`
 *   76         — `instanceof`
 *   82, 84     — `is_array()` and `is_callable()` in an `if` and an `elseif`
 *   88         — `gettype()` compared to a type name
 *   94         — a predicate in a ternary condition
 *   97         — a predicate in a `match` arm condition
 *  102         — a predicate in a `switch` case label, the `switch` counterpart
 *                of line 97
 *  120,121,124 — the argument readers, which need no branch to be a signal: in
 *                a condition, in a nested `if` body, and spelled in upper case
 *                in a `foreach` subject, since PHP resolves function names
 *                case-insensitively
 *  140         — a constructor declared in a trait, on a *promoted* parameter,
 *                so promotion cannot hide a flag from the body scan
 *  148         — `__CONSTRUCT`, since PHP method names are case-insensitive
 *  159         — a constructor of an anonymous class
 */
it('warns on every branching, declaration, and argument-reader shape', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 32, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 34, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 36, 'column' => 20, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 40, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 48, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 54, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 58, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 60, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 62, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 76, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 82, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 84, 'column' => 31, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 88, 'column' => 21, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 94, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 97, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 102, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 120, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 121, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 124, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 140, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 148, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 159, 'column' => 36, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});
