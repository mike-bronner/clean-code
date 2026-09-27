<?php

declare(strict_types=1);

const DISALLOW_LIST_ASSIGNMENT = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

const DISALLOW_LIST_ASSIGNMENT_FOUND = DISALLOW_LIST_ASSIGNMENT . '.Found';

const DISALLOW_LIST_ASSIGNMENT_VIOLATIONS = [
    ['line' => 14, 'column' => 35, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 16, 'column' => 55, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 33, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 26, 'column' => 42, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 30, 'column' => 38, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 34, 'column' => 51, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 44, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 55, 'column' => 78, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 64, 'column' => 59, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 79, 'column' => 46, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 88, 'column' => 47, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 94, 'column' => 46, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 96, 'column' => 47, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 101, 'column' => 54, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 107, 'column' => 45, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_LIST_ASSIGNMENT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a list() assignment in every condition-bearing construct', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect(violationTuples($file))->toBe(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS);
});

it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect($file->getErrorCount())->toBe(count(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect($file->getErrorCount())->toBe(count(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('treats a for header without two section separators as no condition section', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'malformed-for-header.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('refuses an unterminated list() rather than guessing at it', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
