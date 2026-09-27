<?php

declare(strict_types=1);

const CONSTRUCTOR_INSTANTIATION = 'CleanCode.Classes.DisallowConstructorInstantiation';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONSTRUCTOR_INSTANTIATION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns once per instantiation at the new keyword', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 30, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 31, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 41, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(3);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect($file->getWarningCount())->toBe(3)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns on every variant constructor and instantiation shape', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 39, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 49, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 63, 'column' => 29, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 67, 'column' => 31, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 36, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 50, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 88, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 130, 'column' => 15, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 142, 'column' => 24, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 155, 'column' => 15, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
    ]);
});
