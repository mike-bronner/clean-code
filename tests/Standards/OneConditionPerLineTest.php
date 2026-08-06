<?php

/**
 * Tests the custom CleanCode.Conditionals.OneConditionPerLine sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the line =>
 * error-count map below is preserved verbatim from that test's getErrorList().
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

it('flags every violation at its own line', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toBe([
        68 => 1,
        75 => 1,
        84 => 1,
        89 => 1,
        90 => 1,
        97 => 1,
        104 => 1,
        110 => 1,
        117 => 1,
        124 => 1,
        129 => 1,
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

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([121 => [ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine']])
        ->and($file->getFixableCount())->toBe(0);
});
