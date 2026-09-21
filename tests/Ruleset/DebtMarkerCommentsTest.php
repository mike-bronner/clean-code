<?php

/**
 * Integration test for the debt-marker half of Debt: Technical Debt (#24), as
 * wired into the master CleanCode/ruleset.xml by #138 — docs/standards/debt-technical-debt.md.
 *
 * Three sniffs carry one rule. PHPCS core's Generic.Commenting.Todo and
 * Generic.Commenting.Fixme are wired in for TODO and FIXME; the custom
 * CleanCode.Commenting.DebtMarkers sniff carries HACK and XXX, which core
 * ships nothing for. This file owns the wiring verdict — that each of the
 * three resolves through CleanCode/ruleset.xml, that all three report at warning severity,
 * and that between them the four keywords are found in every comment style.
 * The custom sniff's own behaviour is tests/Standards/DebtMarkersTest.php's.
 *
 * Per-sniff fixtures live in tests/fixtures/TodoSniff/ and
 * tests/fixtures/FixmeSniff/; the shared matrix that exercises all three at
 * once lives in tests/fixtures/_rulesets/DebtMarkerComments/.
 */

declare(strict_types=1);

const DEBT_MARKER_SNIFFS = [
    'Generic.Commenting.Todo',
    'Generic.Commenting.Fixme',
    'CleanCode.Commenting.DebtMarkers',
];

it('registers every debt-marker sniff in the master ruleset', function (string $sniffCode): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey($sniffCode);
})->with(DEBT_MARKER_SNIFFS);

it('produces no violations on the compliant fixture', function (string $sniffCode): void {
    $file = analyzeFixture($sniffCode, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'Generic.Commenting.Todo',
    'Generic.Commenting.Fixme',
]);

/**
 * The core pair, each over its own fixture: lines 3 and 4 are the two line
 * comment forms, 6 a single-line block comment, 8 the body of a multi-line
 * block comment, and 14-15 docblock prose. The two codes split on whether a
 * task description follows the keyword.
 */
it('flags the core markers at every line and comment style', function (string $sniffCode): void {
    $file = analyzeFixture($sniffCode, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => $sniffCode . '.TaskFound'],
        ['line' => 4, 'column' => 1, 'source' => $sniffCode . '.TaskFound'],
        ['line' => 6, 'column' => 1, 'source' => $sniffCode . '.TaskFound'],
        ['line' => 8, 'column' => 1, 'source' => $sniffCode . '.CommentFound'],
        ['line' => 14, 'column' => 4, 'source' => $sniffCode . '.CommentFound'],
        ['line' => 15, 'column' => 4, 'source' => $sniffCode . '.TaskFound'],
    ]);
})->with([
    'Generic.Commenting.Todo',
    'Generic.Commenting.Fixme',
]);

/**
 * Generic.Commenting.Fixme calls addError(), not addWarning(); CleanCode/ruleset.xml
 * lowers it with <type>warning</type> so that reaching for FIXME rather than
 * TODO cannot decide whether a consumer's build goes red. Asserted on the
 * severity buckets directly — drop the <type> element and the errors arrive
 * here instead, while every line/code assertion above would still hold.
 */
it('holds FIXME at warning severity rather than the error it ships as', function (): void {
    $file = analyzeFixture('Generic.Commenting.Fixme', 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarningCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The acceptance criteria in one assertion: all four keywords, in each of the
 * comment styles PHP has, over a single file run through all three sniffs at
 * once. Lines 3-6 are slash line comments, 8-11 hash line comments, 13-16
 * single-line block comments, 19-22 the body of a multi-line block comment,
 * and 28-31 docblock prose — each block in keyword order.
 */
it('finds all four markers in every comment style', function (): void {
    $file = analyzeRulesetFixture(DEBT_MARKER_SNIFFS, 'DebtMarkerComments', 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => 'Generic.Commenting.Todo.TaskFound'],
        ['line' => 4, 'column' => 1, 'source' => 'Generic.Commenting.Fixme.TaskFound'],
        ['line' => 5, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.HackTaskFound'],
        ['line' => 6, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.XxxTaskFound'],
        ['line' => 8, 'column' => 1, 'source' => 'Generic.Commenting.Todo.TaskFound'],
        ['line' => 9, 'column' => 1, 'source' => 'Generic.Commenting.Fixme.TaskFound'],
        ['line' => 10, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.HackTaskFound'],
        ['line' => 11, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.XxxTaskFound'],
        ['line' => 13, 'column' => 1, 'source' => 'Generic.Commenting.Todo.TaskFound'],
        ['line' => 14, 'column' => 1, 'source' => 'Generic.Commenting.Fixme.TaskFound'],
        ['line' => 15, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.HackTaskFound'],
        ['line' => 16, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.XxxTaskFound'],
        ['line' => 19, 'column' => 1, 'source' => 'Generic.Commenting.Todo.TaskFound'],
        ['line' => 20, 'column' => 1, 'source' => 'Generic.Commenting.Fixme.TaskFound'],
        ['line' => 21, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.HackTaskFound'],
        ['line' => 22, 'column' => 1, 'source' => 'CleanCode.Commenting.DebtMarkers.XxxTaskFound'],
        ['line' => 28, 'column' => 4, 'source' => 'Generic.Commenting.Todo.TaskFound'],
        ['line' => 29, 'column' => 4, 'source' => 'Generic.Commenting.Fixme.TaskFound'],
        ['line' => 30, 'column' => 4, 'source' => 'CleanCode.Commenting.DebtMarkers.HackTaskFound'],
        ['line' => 31, 'column' => 4, 'source' => 'CleanCode.Commenting.DebtMarkers.XxxTaskFound'],
    ])->and($file->getErrors())->toBe([]);
});

/**
 * The negative case: a file whose comments carry no marker keyword at all
 * raises nothing from any of the three sniffs. Without it, a sniff that
 * reported on every comment it saw would satisfy the matrix above.
 */
it('stays silent on comments carrying no marker', function (): void {
    $file = analyzeRulesetFixture(DEBT_MARKER_SNIFFS, 'DebtMarkerComments', 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
