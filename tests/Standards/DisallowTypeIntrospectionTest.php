<?php

/**
 * Behaviour of CleanCode.Classes.DisallowTypeIntrospection, the custom sniff
 * for the "Classes: Introspection / Type Casting" standard (issue #73).
 * Fixtures live in tests/fixtures/DisallowTypeIntrospectionSniff/.
 *
 * No PHPCS or Slevomat sniff flags type introspection by the role it plays, so
 * this is a custom sniff rather than a rules.xml wiring — the candidates and the
 * reasons they were rejected are in
 * docs/standards/classes-introspection-type-casting.md.
 *
 * There is no autofixed.php because the rule is detection-only: replacing a
 * type check with polymorphism means moving behaviour onto the object and
 * updating its call sites, which no token rewrite can do. The fixable-count
 * test below pins that, so the absent fixture stays an asserted fact.
 *
 * The sniff's whole difficulty is one question — which function body does this
 * token belong to — so most of the fixtures below are about that boundary, from
 * both sides: a check inside a body is a predicate and stays silent, a branch
 * inside the same body is that body's own branch and is reported.
 */

declare(strict_types=1);

const TYPE_INTROSPECTION_SNIFF = 'CleanCode.Classes.DisallowTypeIntrospection';

const TYPE_INTROSPECTION_INSTANCEOF = TYPE_INTROSPECTION_SNIFF . '.InstanceOf';

const TYPE_INTROSPECTION_FUNCTION = TYPE_INTROSPECTION_SNIFF . '.IntrospectionFunction';

/**
 * Every violation in failing.php, in file order, paired with the branch shape
 * it pins. Exact line/column tuples rather than a count, so a violation moving
 * between shapes cannot pass unnoticed.
 */
const TYPE_INTROSPECTION_VIOLATIONS = [
    [11, 20],   // if
    [13, 26],   // elseif
    [22, 23],   // ternary condition
    [28, 20],   // match arm condition
    [29, 20],   // match arm, first condition of a multi-condition arm
    [29, 50],   // match arm, second condition of the same arm
    [37, 25],   // switch case label
    [48, 23],   // while
    [59, 29],   // nested inside an if condition
    [68, 35],   // ternary condition, the check wrapped in a call
    [83, 24],   // if inside a closure body
    [93, 53],   // ternary inside an arrow function body
    [100, 24],  // match arm inside a closure body
    [110, 29],  // switch case label inside a closure body
    [125, 20],  // a plain if, after every callback above has closed
    [138, 78],  // if condition, after a callback closed within that same condition
    [150, 81],  // ternary condition, after a callback closed within that same condition
    [168, 27],  // ternary inside a closure body
    [175, 20],  // match arm inside an arrow function body
    [187, 60],  // match subject inside an arrow function body
];

/**
 * Every violation in function-scope-branches.php: a branch written inside an
 * inline anonymous class's method, which the enclosing `if` condition then
 * branches on the result of.
 *
 * This is the over-suppression direction of the scope rule. A bound that
 * exempted the whole body instead of confining the search to it would report
 * none of these, and the sniff would go quiet on real violations.
 */
const TYPE_INTROSPECTION_INLINE_METHOD_VIOLATIONS = [
    [31, 28, TYPE_INTROSPECTION_INSTANCEOF],   // if inside the method body
    [52, 31, TYPE_INTROSPECTION_INSTANCEOF],   // ternary inside the method body
    [70, 28, TYPE_INTROSPECTION_INSTANCEOF],   // match arm inside the method body
    [90, 33, TYPE_INTROSPECTION_INSTANCEOF],   // switch case label inside the method body
    [111, 24, TYPE_INTROSPECTION_FUNCTION],    // while inside the method body
    [133, 21, TYPE_INTROSPECTION_FUNCTION],    // introspection function in an if inside the body
    [159, 20, TYPE_INTROSPECTION_INSTANCEOF],  // a plain if, after the inline body has closed
    [183, 20, TYPE_INTROSPECTION_INSTANCEOF],  // a concrete method, after bodyless declarations
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TYPE_INTROSPECTION_SNIFF);
});

/**
 * passing.php is the contract sweep's floor fixture, and it is discriminating
 * rather than empty: every construct the sniff registers on appears in it, in
 * the roles that are *not* a branch decision — an exception message, a log
 * line, an assertion, a `return $x instanceof Y;` predicate, a branch body, and
 * a callback passed into each kind of condition.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The rewrite the standard actually asks for: the type declared in the
 * signature and the varying behaviour moved onto the object, so nothing has to
 * ask what type anything is.
 */
it('produces no violations on the polymorphic rewrite', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'polymorphic-rewrite.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags instanceof driving a branch at its exact position', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'failing.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_INSTANCEOF,
        ],
        TYPE_INTROSPECTION_VIOLATIONS
    );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every introspection *function* the sniff knows, each in a different branch
 * position, plus the two shapes that look like an exemption and are not: a
 * root-namespaced call, and a `...` that is a variadic unpack rather than the
 * first-class callable syntax.
 */
it('flags introspection functions driving a branch at their exact position', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'introspection-functions.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [11, 13],  // get_class in an if
            [20, 16],  // get_debug_type in a ternary
            [25, 17],  // gettype as a switch subject
            [36, 13],  // is_a in a match arm
            [37, 13],  // is_subclass_of in a match arm
            [44, 14],  // a root-namespaced \get_class()
            [53, 13],  // an upper-cased call
            [64, 16],  // is_subclass_of in a while
            [76, 18],  // gettype in a case label
            [92, 13],  // a variadic unpack, which really does call
        ]
    );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('names the offending call in the message', function (): void {
    $errors = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'introspection-functions.php')->getErrors();

    expect($errors[11][13][0]['message'])->toContain('get_class()')
        ->and($errors[37][13][0]['message'])->toContain('is_subclass_of()');
});

/**
 * The scope rule, silent direction. Every form of function body PHP has —
 * closure, arrow function, and a method written inline as an anonymous class —
 * paired against every branch position the sniff recognises. In each cell the
 * check decides what the body returns and the enclosing construct branches on
 * the result of calling it, so it is a predicate, not a branch decision.
 *
 * Filling the grid is the point. Deciding what bounds the search by listing
 * keywords left the third form out, and it was the form that looks least like a
 * callback while playing exactly the same role.
 */
it('does not flag a check inside any function body written in a condition', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'function-scopes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still flags a branch inside that same function body', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'function-scope-branches.php');

    $expected = array_map(
        static fn (array $violation): array => [
            'line' => $violation[0],
            'column' => $violation[1],
            'source' => $violation[2],
        ],
        TYPE_INTROSPECTION_INLINE_METHOD_VIOLATIONS
    );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A `use function` import rebinds the bare name, so the call reaches the
 * imported function rather than the global one. The controls keep the test from
 * passing vacuously: a name this file does not import, and a root-qualified
 * call, which is the global function whatever the bare name resolves to.
 */
it('does not treat an imported name as the global introspection function', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-import.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [64, 13],  // an introspection function this file does not import
            [77, 14],  // a root-qualified \get_class()
        ]
    );

    expect(violationTuples($file))->toBe($expected);
});

/**
 * A function of the same name declared in the file shadows the global one. The
 * controls again: a root-qualified call, a name declared only as a *method* —
 * which an unqualified call never reaches, so it shadows nothing — and a name
 * the file does not declare at all.
 */
it('does not treat a name declared as a function in the file as the global one', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-declaration.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [37, 14],  // a root-qualified \get_class()
            [51, 13],  // a name declared only as a method
            [69, 13],  // a name the file does not declare
        ]
    );

    expect(violationTuples($file))->toBe($expected);
});

it('reports every violation as non-fixable', function (string $fixture): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, $fixture);

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    'failing.php',
    'function-scope-branches.php',
    'introspection-functions.php',
    'shadowed-by-declaration.php',
    'shadowed-by-import.php',
]);
