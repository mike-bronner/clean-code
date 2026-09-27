<?php

declare(strict_types=1);

const NOT_OPERATOR_SPACING = 'CleanCode.Operators.NotOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NOT_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => NOT_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 11, 'column' => 12, 'source' => NOT_OPERATOR_SPACING . '.SpaceBefore'],
        ['line' => 13, 'column' => 5, 'source' => NOT_OPERATOR_SPACING . '.TooMuchSpaceAfter'],
        ['line' => 17, 'column' => 17, 'source' => NOT_OPERATOR_SPACING . '.SpaceBefore'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});
