<?php

declare(strict_types=1);

pest()->group('arch');

const MANUAL_RESOLUTION = 'CleanCode.Controllers.ManualModelResolution';

const MANUAL_RESOLUTION_WARNING = MANUAL_RESOLUTION . '.Found';

$warningsByPosition = static function (string $fixture): array {
    $map = [];

    foreach (analyzeFixture(MANUAL_RESOLUTION, $fixture)->getWarnings() as $line => $columns) {
        foreach ($columns as $column => $messages) {
            foreach ($messages as $message) {
                $map[$line][$column] = $message['source'];
            }
        }
    }

    return $map;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MANUAL_RESOLUTION);
});

it('passes the standard it belongs to', function (): void {
    $report = installedPhpcsReport(
        'CleanCode',
        cleanCodeRoot() . '/CleanCode/Sniffs/Controllers/ManualModelResolutionSniff.php'
    );

    expect(array_column($report, 'source'))->toBe([]);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MANUAL_RESOLUTION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every manual resolution at its position', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'failing.php')->getErrors())->toBe([])
        ->and($warningsByPosition('failing.php'))->toBe([
            10 => [22 => MANUAL_RESOLUTION_WARNING],
            15 => [22 => MANUAL_RESOLUTION_WARNING],
            20 => [22 => MANUAL_RESOLUTION_WARNING],
            25 => [22 => MANUAL_RESOLUTION_WARNING],
            30 => [29 => MANUAL_RESOLUTION_WARNING],
            35 => [22 => MANUAL_RESOLUTION_WARNING],
            40 => [22 => MANUAL_RESOLUTION_WARNING],
            45 => [33 => MANUAL_RESOLUTION_WARNING],
            51 => [26 => MANUAL_RESOLUTION_WARNING],
            57 => [22 => MANUAL_RESOLUTION_WARNING],
            62 => [22 => MANUAL_RESOLUTION_WARNING],
            67 => [22 => MANUAL_RESOLUTION_WARNING],
        ]);
});

it('names the model, the parameter and the action in the warning', function (): void {
    $warnings = analyzeFixture(MANUAL_RESOLUTION, 'failing.php')->getWarnings();

    expect($warnings[20][22][0]['message'])
        ->toContain('Model User')
        ->toContain('$id')
        ->toContain('shout()');
});

it('names a qualified model by its class name alone', function (): void {
    $warnings = analyzeFixture(MANUAL_RESOLUTION, 'failing.php')->getWarnings();

    expect($warnings[30][29][0]['message'])
        ->toContain('Model User')
        ->not->toContain('Models\\');
});

it('judges nested scopes by the enclosing method', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'nested-scopes.php')->getErrors())->toBe([])
        ->and($warningsByPosition('nested-scopes.php'))->toBe([
            10 => [26 => MANUAL_RESOLUTION_WARNING],
            16 => [66 => MANUAL_RESOLUTION_WARNING],
            24 => [30 => MANUAL_RESOLUTION_WARNING],
        ]);
});

it('handles mid-edit source without falling over', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'unterminated.php')->getErrors())->toBe([])
        ->and($warningsByPosition('unterminated.php'))->toBe([
            9 => [22 => MANUAL_RESOLUTION_WARNING],
        ]);
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(MANUAL_RESOLUTION, 'failing.php');

    expect($file->getWarningCount())->toBe(12)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
