<?php

declare(strict_types=1);

const IF_STATEMENT_ASSIGNMENT_SNIFF = 'Generic.CodeAnalysis.AssignmentInCondition';

const IF_STATEMENT_ASSIGNMENT_LIST_SNIFF = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

const IF_STATEMENT_ASSIGNMENT_FOUND = IF_STATEMENT_ASSIGNMENT_SNIFF . '.Found';

const IF_STATEMENT_ASSIGNMENT_IN_WHILE = IF_STATEMENT_ASSIGNMENT_SNIFF . '.FoundInWhileCondition';

const IF_STATEMENT_ASSIGNMENT_SHARED = [
    ['line' => 14, 'column' => 18, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 16, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 20, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 35, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 24, 'column' => 28, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 37, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 32, 'column' => 29, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 36, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 40, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 20, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 45, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 56, 'column' => 41, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
];

const IF_STATEMENT_ASSIGNMENT_BROADER = [
    ['line' => 20, 'column' => 21, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 24, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 36, 'column' => 36, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 26, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 45, 'column' => 25, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 49, 'column' => 32, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 57, 'column' => 16, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
];

const IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS = [
    ['line' => 32, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_IN_WHILE],
    ['line' => 42, 'column' => 26, 'source' => IF_STATEMENT_ASSIGNMENT_IN_WHILE],
];

const IF_STATEMENT_ASSIGNMENT_LIST_GAP = [
    ['line' => 18, 'column' => 35, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
    ['line' => 20, 'column' => 55, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
    ['line' => 24, 'column' => 33, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(IF_STATEMENT_ASSIGNMENT_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'passing.php')
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every assignment PHPMD reports, at the same line', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_SHARED);
});

it('flags the conditions PHPMD overlooks too', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_BROADER);
});

it('lowers the while-condition code to a warning and leaves every other condition an error', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(warningTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS)
        ->and(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_BROADER)
        ->and($file->getWarningCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS))
        ->and($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_BROADER));
});

it('keeps the while-condition code reporting rather than excluding it', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(array_column(warningTuples($file), 'source'))
        ->toBe(array_fill(0, count(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS), IF_STATEMENT_ASSIGNMENT_IN_WHILE));
});

it('closes the list() destructuring gap with the custom sniff', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'list-gap.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_LIST_GAP);
});

it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_SHARED))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_SHARED))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
