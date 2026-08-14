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
 * assertions stay stable as sibling standards land in rules.xml.
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
    $byLine = violationSourcesByLine(analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php')->getErrors());

    expect(array_keys($byLine))->toBe([5, 8, 12, 16, 19, 22]);

    foreach ($byLine as $sources) {
        expect($sources)->toBe([OPERATOR_LINE_BREAK . '.OperatorAtLineEnd']);
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
    $byLine = violationSourcesByLine(
        analyzeFixture(OPERATOR_LINE_BREAK, 'reported-conditional.php')->getErrors()
    );

    expect(array_keys($byLine))->toBe([9, 19, 29]);

    foreach ($byLine as $sources) {
        expect($sources)->toBe([OPERATOR_LINE_BREAK . '.OperatorAtLineEnd']);
    }
});

it('reports violations that are not auto-fixable', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
