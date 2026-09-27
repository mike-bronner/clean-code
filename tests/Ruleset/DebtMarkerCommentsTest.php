<?php

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

it('holds FIXME at warning severity rather than the error it ships as', function (): void {
    $file = analyzeFixture('Generic.Commenting.Fixme', 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarningCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(0);
});

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

it('stays silent on comments carrying no marker', function (): void {
    $file = analyzeRulesetFixture(DEBT_MARKER_SNIFFS, 'DebtMarkerComments', 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
