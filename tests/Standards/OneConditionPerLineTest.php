<?php

/**
 * Tests the custom CleanCode.Conditionals.OneConditionPerLine sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the lines
 * pinned below are preserved verbatim from that test's getErrorList(), with the
 * column and violation code of each report added.
 * Fixtures moved from
 * CleanCode/Tests/Conditionals/OneConditionPerLineUnitTest.inc (+ .inc.fixed)
 * to tests/fixtures/OneConditionPerLineSniff/failing.php (+ autofixed.php).
 */

declare(strict_types=1);

const ONE_CONDITION_PER_LINE = 'CleanCode.Conditionals.OneConditionPerLine';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ONE_CONDITION_PER_LINE);
});

/**
 * A genuinely separate compliant fixture, not a reuse of autofixed.php: that
 * file deliberately retains the non-fixable split-condition-wrapping-a-comment
 * case (pinned below), so it is not clean and could never stand in for one.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 68, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 75, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 84, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 89, 'column' => 15, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 90, 'column' => 17, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 97, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 104, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 110, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 117, 'column' => 3, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 124, 'column' => 3, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 129, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
    ])->and($file->getWarnings())->toBe([]);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('OneConditionPerLineSniff', 'autofixed.php')));
});

/**
 * The fixer is deliberately partial. A single condition split across lines
 * *around a comment* is reported but withheld from the fixer, because
 * rejoining the condition would have to decide where the comment goes — so
 * autofixed.php legitimately still carries that one violation, and it is
 * non-fixable rather than merely unfixed.
 *
 * This is why the generic contract sweep asserts idempotence for this sniff
 * but not cleanliness. Pinned here so that a fixer which later learned to
 * rewrite the comment case — or one which regressed into silently dropping the
 * report — surfaces as a failure rather than as a quietly changed fixture.
 */
it('reports but does not fix a split single condition wrapping a comment', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'autofixed.php');

    expect(violationTuples($file))
        ->toBe([
            ['line' => 121, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ])
        ->and($file->getFixableCount())->toBe(0);
});
