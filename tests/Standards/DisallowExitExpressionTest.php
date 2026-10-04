<?php

declare(strict_types=1);

const DISALLOW_EXIT_EXPRESSION = 'CleanCode.ControlStructures.DisallowExitExpression';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_EXIT_EXPRESSION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every exit expression at its own position', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    $positions = array_map(
            static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
            violationTuples($file)
        );

    expect($positions)->toBe([
        [5, 5],
        [10, 5],
        [15, 5],
        [20, 5],
        [25, 5],
        [30, 5],
        [35, 5],
        [41, 9],
        [47, 22],
        [54, 9],
        [60, 13],
        [68, 32],
        [76, 17],
        [86, 9],
        [96, 9],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports every violation under one message code', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    $sources = array_unique(array_column(violationTuples($file), 'source'));

    expect($sources)->toBe([DISALLOW_EXIT_EXPRESSION . '.Found']);
});

it('quotes the construct as it was written', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');
    $errors = $file->getErrors();

    expect($errors[30][5][0]['message'])->toStartWith('Exit expression EXIT ')
        ->and($errors[35][5][0]['message'])->toStartWith('Exit expression Die ')
        ->and($errors[5][5][0]['message'])->toStartWith('Exit expression exit ')
        ->and($errors[20][5][0]['message'])->toStartWith('Exit expression die ');
});

it('quotes a fully qualified construct without its leading separator', function (): void {
    $errors = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'divergences.php')->getErrors();

    expect($errors[36][5][0]['message'])->toStartWith('Exit expression exit ')
        ->and($errors[41][5][0]['message'])->toStartWith('Exit expression die ');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the shapes PHPMD misses', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'divergences.php');

    $positions = array_map(
            static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
            violationTuples($file)
        );

    expect($positions)->toBe([
        [23, 9],
        [30, 9],
        [36, 5],
        [41, 5],
    ]);
});
