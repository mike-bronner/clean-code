<?php

declare(strict_types=1);

const DISALLOW_DEBUG_FUNCTIONS = 'CleanCode.Debug.DisallowDebugFunctions';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_DEBUG_FUNCTIONS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every debug call at its own line', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found',
        ],
        [
            [3, 1], [4, 1], [5, 1], [6, 1], [7, 1],
            [22, 11],
            [24, 2], [26, 1], [27, 2], [28, 1], [29, 1], [30, 2], [31, 1],
            [32, 2],
        ]
    ))->and($file->getWarnings())->toBe([]);
});

it('flags every debug call an import did not bind', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'imported-names.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
        ['line' => 14, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
        ['line' => 15, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

it('stays silent on a namespace-relative call inside a declared namespace', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'namespace-relative.php');

    expect(violationTuples($file))
        ->toBe([['line' => 22, 'column' => 2, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found']])
        ->and($file->getWarnings())->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
