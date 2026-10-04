<?php

declare(strict_types=1);

const EAGER_LOADING_WITH = 'CleanCode.Models.DisallowAlwaysOnEagerLoading';

const EAGER_LOADING_WITH_WARNING = EAGER_LOADING_WITH . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EAGER_LOADING_WITH);
});

it('flags a populated $with on every model-shaped parent', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'failing.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            5 => [EAGER_LOADING_WITH_WARNING],
            10 => [EAGER_LOADING_WITH_WARNING],
            15 => [EAGER_LOADING_WITH_WARNING],
            20 => [EAGER_LOADING_WITH_WARNING],
            25 => [EAGER_LOADING_WITH_WARNING],
            30 => [EAGER_LOADING_WITH_WARNING],
            35 => [EAGER_LOADING_WITH_WARNING],
        ])
        ->and(array_map('array_keys', $warnings))->toBe([
            5 => [15],
            10 => [15],
            15 => [15],
            20 => [15],
            25 => [15],
            30 => [13],
            35 => [12],
        ]);
});

it('names the query-site alternative in the warning message', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'failing.php')->getWarnings();

    expect($warnings[5][15][0]['message'])
        ->toContain('with()')
        ->toContain('resources/boost/guidelines/models-eager-loading.md');
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a constructor-promoted $with', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'promoted.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            5 => [EAGER_LOADING_WITH_WARNING],
            13 => [EAGER_LOADING_WITH_WARNING],
        ])
        ->and(array_map('array_keys', $warnings))->toBe([
            5 => [46],
            13 => [58],
        ]);
});

it('skips comments between the property name and the assignment', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'spaced-before-assignment.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

it('skips comments between the assignment and the array literal', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'spaced-before-array.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

it('exposes a configurable model-parent list', function (): void {
    expect(analyzeFixture(EAGER_LOADING_WITH, 'configured.php')->getWarnings())->toBe([]);

    $warnings = analyzeFixture(
            EAGER_LOADING_WITH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->modelParentClasses = ['eloquent'];
            }
        )->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

it('passes over half-written declarations without falling over', function (string $fixture): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'truncated.php',
    'no-default.php',
    'no-value.php',
    'unclosed-short-array.php',
    'unclosed-long-array.php',
]);

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'failing.php');

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
