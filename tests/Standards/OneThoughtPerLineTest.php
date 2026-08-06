<?php

/**
 * Tests the custom CleanCode.ClearCode.OneThoughtPerLine sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the line =>
 * error-count map below is preserved verbatim from that test's getErrorList().
 * Fixtures moved from CleanCode/Tests/ClearCode/OneThoughtPerLineUnitTest.inc
 * (+ .inc.fixed) to tests/fixtures/OneThoughtPerLineSniff/failing.php
 * (+ autofixed.php).
 *
 * Line 25 carries two errors — a chain broken across lines more than once in a
 * single statement — which is what keeps the map a count map rather than a
 * plain list of lines.
 */

declare(strict_types=1);

const ONE_THOUGHT_PER_LINE = 'CleanCode.ClearCode.OneThoughtPerLine';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ONE_THOUGHT_PER_LINE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toBe([
        25 => 2,
        26 => 1,
        27 => 1,
        28 => 1,
        29 => 1,
        30 => 1,
        31 => 1,
        34 => 1,
        36 => 1,
        37 => 1,
        38 => 1,
        42 => 1,
    ])->and($file->getWarnings())->toBe([]);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('OneThoughtPerLineSniff', 'autofixed.php')));
});
