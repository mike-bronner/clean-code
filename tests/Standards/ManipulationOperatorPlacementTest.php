<?php

/**
 * Tests the custom CleanCode.Operators.ManipulationOperatorPlacement sniff
 * (Operators: Manipulative, #59). Fixtures live in
 * tests/fixtures/ManipulationOperatorPlacementSniff/.
 *
 * The sniff is isolated from the rest of the master ruleset so these assertions
 * stay stable as sibling standards land in rules.xml. The one place that
 * *needs* the whole ruleset — that no other sniff reports the same wrap — is
 * tests/Integration/OperatorRulesIntegrationTest.php, which pins every operator
 * source line by line.
 */

declare(strict_types=1);

const MANIPULATION_OPERATOR_PLACEMENT = 'CleanCode.Operators.ManipulationOperatorPlacement';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MANIPULATION_OPERATOR_PLACEMENT);
});

/**
 * The math group (`+ - * / % **`) and the bitwise group (`& | ^ << >>`) — the
 * slice of the standard's operator list no other rule already polices. The
 * assertion is over register() itself rather than over a fixture, so it holds
 * for every token either side claims rather than for whichever ones an example
 * happens to use.
 */
it('registers the math and bitwise operators only', function (): void {
    $registered = (new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff())->register();

    sort($registered);

    $expected = [T_PLUS, T_MINUS, T_MULTIPLY, T_DIVIDE, T_MODULUS, T_POW, T_BITWISE_AND, T_BITWISE_OR,
        T_BITWISE_XOR, T_SL, T_SR];

    sort($expected);

    expect($registered)->toBe($expected);
});

/**
 * The disjointness the split rests on, asserted the same way
 * tests/Standards/BooleanOperatorSpacingTest.php asserts its own: the two
 * sniffs enforcing "an operator must lead the continuation line" share no
 * token, so no wrapped expression is ever reported twice. `.` and the boolean
 * connectives stay with OperatorLineBreak (#35); `~` belongs to neither,
 * having no binary form.
 */
it('shares no token with the sniff that owns the rest of the rule', function (): void {
    $manipulation = (new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff())->register();
    $lineBreak = (new MikeBronner\CleanCode\Sniffs\Operators\OperatorLineBreakSniff())->register();

    expect(array_intersect($manipulation, $lineBreak))->toBe([])
        ->and($lineBreak)->toContain(T_STRING_CONCAT, T_BOOLEAN_AND, T_BOOLEAN_OR)
        ->and($manipulation)->not->toContain(T_BITWISE_NOT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every trailing manipulation operator in failing.php, at its exact line and
 * column. Line 89 is the comment case — reported, but not fixable.
 */
it('flags every trailing operator at its exact line and column', function (): void {
    $tuples = violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 6, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 9, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 10, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 13, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 16, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 19, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 23, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 24, 'column' => 12, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 27, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 28, 'column' => 10, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 29, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 36, 'column' => 19, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 39, 'column' => 26, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 44, 'column' => 9, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 49, 'column' => 9, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 56, 'column' => 12, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 61, 'column' => 11, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 71, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 81, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 89, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
    ]);
});

/**
 * Moving the operator across a comment sitting between the two operands would
 * reorder the comment, so that one violation is reported without a fixer while
 * every other one carries one. Asserted per violation rather than as a count,
 * so a fixer that silently stopped offering itself elsewhere would fail here.
 */
it('offers a fix for every violation except the one behind a comment', function (): void {
    $fixable = violationFixableFlags(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    // The flags come back in report order, so the comment case is the last of
    // the twenty violations the test above pins line by line.
    expect($fixable)->toBe(array_merge(array_fill(0, 19, true), [false]));
});

/**
 * The fixer's own output, byte for byte, is asserted by the contract sweep. The
 * point of interest here is *where* the operator lands: one level past the
 * statement's root line, level with its operand — including when the operator
 * sits inside an `if (...)` condition, a call-argument list, or an array
 * literal, which is where an anchor resolved with findStartOfStatement() alone
 * would indent one level too deep.
 */
it('fixes a bracketed operator to the statement root indent', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("\$called = someCall(\n    \$value\n    + \$four\n);")
        ->and($fixed)->toContain("\$array = [\n    \$base\n    - \$discount,\n];")
        ->and($fixed)->toContain("    return between(\n        \$left\n        + \$right\n    );");
});

/**
 * A `|` separating exception types in a multi-line `catch` clause stays
 * T_BITWISE_OR — PHP_CodeSniffer only retokenises a union to T_TYPE_UNION in
 * parameter, return, and property positions — so without the catch-clause
 * exemption the sniff would flag valid code and the fixer would reformat it.
 * Pinned by mutating the fixture rather than the sniff: the same shape outside
 * a catch clause is still reported.
 */
it('leaves a multi-line catch type union alone while still flagging a real bitwise or', function (): void {
    $passing = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');
    $failing = violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($passing->getErrors())->toBe([])
        ->and(array_column($failing, 'line'))->toContain(27);
});
