<?php

/**
 * Tests the custom CleanCode.Operators.OperatorLineBreak sniff (Arrays:
 * Operator spacing & line breaks, #35). Fixtures live in
 * tests/fixtures/OperatorLineBreakSniff/.
 *
 * This rule is report-only — where the operator lands on the rewritten line is
 * a layout judgement — so there is no autofixed fixture; the not-auto-fixable
 * test pins that decision.
 *
 * The sniff is isolated from the rest of the master ruleset so these
 * assertions stay stable as sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const OPERATOR_LINE_BREAK = 'CleanCode.Operators.OperatorLineBreak';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(OPERATOR_LINE_BREAK);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Logical (&&, line 5), concatenation (., lines 8/12/22), assignment
 * (=, line 16) and comparison (===, line 19) operators left dangling at the
 * end of a wrapped line. Line 22 dangles behind a trailing comment — flagged
 * because the sniff skips comments when locating the next code token.
 */
it('flags every dangling operator at its line', function (): void {
    $tuples = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php'));
    $expected = [[5, 19], [8, 22], [12, 29], [16, 9], [19, 16], [22, 12]];

    expect($tuples)->toHaveCount(count($expected));

    foreach ($expected as $index => [$line, $column]) {
        expect($tuples[$index])->toBe([
            'line' => $line,
            'column' => $column,
            'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd',
        ]);
    }
});

/**
 * Operators dangling inside an if/elseif/while/for condition are owned by
 * CleanCode.Conditionals.OneConditionPerLine (which can auto-fix them), so
 * this sniff defers — otherwise the same wrap is reported twice. Both the
 * logical `||` and the comparison `===` in the fixture would flag without
 * the deferral.
 */
it('defers dangling operators inside conditions', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'deferred-conditional.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A non-boolean operator (comparison, assignment, concatenation) dangling
 * inside a *multi*-condition control structure is owned by neither this
 * sniff's old blanket deferral nor OneConditionPerLine (which polices only
 * boolean-operator placement there), so it must be reported here. Covers
 * the top-level case (=== / . beside a top-level ||/&&) and the nested case
 * (=== inside an inner grouping parenthesis).
 */
it('reports a dangling non-boolean operator in a multi-condition', function (): void {
    $tuples = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'reported-conditional.php'));
    $expected = [[9, 8], [19, 14], [29, 13], [42, 24], [45, 21]];

    expect($tuples)->toHaveCount(count($expected));

    foreach ($expected as $index => [$line, $column]) {
        expect($tuples[$index])->toBe([
            'line' => $line,
            'column' => $column,
            'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd',
        ]);
    }
});

/**
 * The boolean half of the same boundary, which is this sniff's alone. A
 * for-loop's init and increment clauses sit inside the condition's parentheses,
 * so the deferral used to hand every operator in them to
 * CleanCode.Conditionals.OneConditionPerLine — which confines every check it
 * makes to the clause between the two semicolons and so never reported them.
 * The violation evaporated. Support\ConditionOperatorOwnership now scopes the
 * deferral to that same clause, and this pins the result where it is visible:
 * a dangling `&&` in the init clause and a dangling `||` in the increment one,
 * both reported here.
 *
 * The condition clause is the discriminator on the other side — the `||` of
 * deferred-conditional.php, which this sniff must still stand down on, or the
 * same wrap is reported twice.
 */
it('reports a dangling boolean outside the clause it defers', function (): void {
    $reported = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'reported-conditional.php'));
    $deferred = analyzeFixture(OPERATOR_LINE_BREAK, 'deferred-conditional.php');

    $inForHeader = array_values(array_filter(
        $reported,
        static fn (array $violation): bool => in_array($violation['line'], [42, 45], true)
    ));

    expect($inForHeader)->toHaveCount(2)
        ->and($inForHeader[0])
        ->toBe(['line' => 42, 'column' => 24, 'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd'])
        ->and($inForHeader[1])
        ->toBe(['line' => 45, 'column' => 21, 'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd'])
        ->and($deferred->getErrors())->toBe([]);

    // Both lines are genuinely a boolean-terminated wrap inside a for header's
    // init and increment clauses, so a fixture edit cannot make this vacuous.
    $source = file(fixturePath(sniffFixtureDirectory(OPERATOR_LINE_BREAK), 'reported-conditional.php'));

    expect(trim($source[41]))->toBe('$ready = $isActive &&')
        ->and(trim($source[44]))->toBe('$ready = $ready ||');
});

/**
 * Every violation is fixable, because the fix is position only: the token
 * sequence is unchanged and just the line break moves from after the operator
 * to before it. Asserted as parity with the error count rather than as a fixed
 * number, so a fixer that started declining a shape fails here.
 */
it('fixes every violation it reports', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});
