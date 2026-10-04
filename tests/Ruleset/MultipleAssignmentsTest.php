<?php

declare(strict_types=1);

const MULTIPLE_ASSIGNMENTS_SNIFF = 'Squiz.PHP.DisallowMultipleAssignments';

const MULTIPLE_ASSIGNMENTS_FOUND = MULTIPLE_ASSIGNMENTS_SNIFF . '.Found';

const MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE = MULTIPLE_ASSIGNMENTS_SNIFF . '.FoundInControlStructure';

const ASSIGNMENT_IN_CONDITION_SNIFF = 'Generic.CodeAnalysis.AssignmentInCondition';

$multipleAssignmentsPackageFiles = static function (): array {
    $root = cleanCodeRoot();
    $paths = [];

    foreach (['CleanCode', 'tests'] as $tree) {
        $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS)
            );

        foreach ($entries as $entry) {
            $path = $entry->getPathname();

            if (str_ends_with($path, '.php') === true && str_contains($path, '/fixtures/') === false) {
                $paths[] = $path;
            }
        }
    }

    sort($paths);

    return $paths;
};

const MULTIPLE_ASSIGNMENTS_CHAINED = [
    ['line' => 18, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 20, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 20, 'column' => 35, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 22, 'column' => 35, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 24, 'column' => 40, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 35, 'column' => 29, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
];

const MULTIPLE_ASSIGNMENTS_BOUNDARIES = [
    ['line' => 25, 'column' => 21, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
    ['line' => 30, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
    ['line' => 34, 'column' => 25, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 39, 'column' => 32, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTIPLE_ASSIGNMENTS_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'passing.php')
        );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every binding beyond the first in a chained assignment', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
        );

    expect(violationTuples($file))->toBe(MULTIPLE_ASSIGNMENTS_CHAINED);
});

it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
        );

    expect($file->getErrorCount())->toBe(count(MULTIPLE_ASSIGNMENTS_CHAINED))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
        );

    expect($file->getErrorCount())->toBe(count(MULTIPLE_ASSIGNMENTS_CHAINED))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('separates the control-structure code from the plain one', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
        );

    expect(violationTuples($file))->toBe(MULTIPLE_ASSIGNMENTS_BOUNDARIES)
        ->and($file->getWarnings())->toBe([]);
});

it('leaves a chain inside a while header to the sibling sniff, at warning severity', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
        );

    expect(warningTuples($file))->toBe([
        ['line' => 47, 'column' => 25, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
        ['line' => 47, 'column' => 35, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
        ['line' => 54, 'column' => 26, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
    ]);
});

it('reports no errors from either rule against the package source', function () use (
    $multipleAssignmentsPackageFiles
): void {
    $sources = [];
    $warnings = 0;

    foreach ($multipleAssignmentsPackageFiles() as $path) {
        $file = analyzeWithSniffs([MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF], $path);

        $sources = array_merge($sources, array_column(violationTuples($file), 'source'));
        $warnings += $file->getWarningCount();
    }

    expect($sources)->toBe([])
        ->and($warnings)->toBe(27);
});

it('reports a condition assignment under both rules', function (): void {
    $file = analyzeWithSniffs(
            [MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF],
            fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
        );

    expect(violationTuples($file))->toBe([
        ['line' => 25, 'column' => 21, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 25, 'column' => 21, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 30, 'column' => 26, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 30, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 34, 'column' => 25, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 34, 'column' => 25, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
        ['line' => 39, 'column' => 32, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 39, 'column' => 32, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 61, 'column' => 34, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
    ]);
});
