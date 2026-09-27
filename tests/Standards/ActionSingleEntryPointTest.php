<?php

declare(strict_types=1);

const ACTION_SINGLE_ENTRY_POINT = 'CleanCode.ClearCode.ActionSingleEntryPoint';

const ACTION_SINGLE_ENTRY_POINT_WARNING = ACTION_SINGLE_ENTRY_POINT . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ACTION_SINGLE_ENTRY_POINT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every public method past the first entry point', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 16, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 33, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 37, 'column' => 23, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 41, 'column' => 9, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 45, 'column' => 25, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 70, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 89, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

it('names the offending method in the warning message', function (): void {
    $warnings = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php')->getWarnings();

    expect($warnings[70][16][0]['message'])->toContain('getResult()');
});

it('ignores declarations nested inside an entry point', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'nested-declarations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 52, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

it('ignores functions declared inside a property hook body', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'property-hooks.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 68, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

it('steps over a trait-adaptation block', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'trait-adaptations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 34, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

it('reports what it read before an unresolvable declaration', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 19, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

it('reports nothing on an unterminated class body', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('leaves the package own source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/CleanCode/*/*/*.php');

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([ACTION_SINGLE_ENTRY_POINT], $path);

        expect($file->numTokens)->toBeGreaterThan(0)
            ->and(warningTuples($file))->toBe([])
            ->and($file->getErrors())->toBe([]);
    }
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php');

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
