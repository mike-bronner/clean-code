<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const UNUSED_LOCAL_VARIABLE_SNIFF = 'VariableAnalysis.CodeAnalysis.VariableAnalysis';

const UNUSED_VARIABLE = UNUSED_LOCAL_VARIABLE_SNIFF . '.UnusedVariable';

const UNUSED_LOCAL_PARITY_SET = [
    [23, 9],
    [37, 27],
    [51, 27],
    [65, 35],
    [77, 17],
    [88, 16],
    [98, 16],
    [110, 13],
    [122, 9],
    [133, 9],
];

$parityTuples = static function (array $withoutLines = []): array {
    $tuples = [];

    foreach (UNUSED_LOCAL_PARITY_SET as [$line, $column]) {
        if (in_array($line, $withoutLines, true)) {
            continue;
        }

        $tuples[] = ['line' => $line, 'column' => $column, 'source' => UNUSED_VARIABLE];
    }

    return $tuples;
};

$analyzeOverridden = static function (string $fixture, string $property, $value): LocalFile {
    return analyzeFixture(
            UNUSED_LOCAL_VARIABLE_SNIFF,
            $fixture,
            static function (object $sniff) use ($property, $value): void {
                $sniff->{$property} = $value;
            }
        );
};

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each unused local at its own line and column', function () use ($parityTuples): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect(violationTuples($file))->toBe($parityTuples());
});

it('reports unused locals as errors rather than warnings', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('reports unused locals without offering an auto-fix', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(UNUSED_LOCAL_PARITY_SET), false));
});

it('round-trips the unused-locals fixture byte-identically through the fixer', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET));

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('VariableAnalysisSniff', 'unused-locals.php')))
        ->and($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET));
});

it('leaves an unused formal parameter to the UnusedFormalParameter rule', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same parameter without the configured property', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedFunctionParameters', false);

    expect(violationTuples($file))->toBe([
        ['line' => 81, 'column' => 44, 'source' => UNUSED_VARIABLE],
    ]);
});

it('ignores an unused assignment in the file scope', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same file-scope assignment without the property', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedVariablesInFileScope', false);

    expect(violationTuples($file))->toBe([
        ['line' => 168, 'column' => 1, 'source' => UNUSED_VARIABLE],
    ]);
});

it('ignores a caught exception nobody reads', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same caught exception without the sniff default', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedCaughtExceptions', false);

    expect(violationTuples($file))->toBe([
        ['line' => 94, 'column' => 28, 'source' => UNUSED_VARIABLE],
    ]);
});

it('silences only the associative foreach value when the property is flipped', function () use (
    $analyzeOverridden,
    $parityTuples
): void {
    $file = $analyzeOverridden('unused-locals.php', 'allowUnusedForeachVariables', true);

    expect(violationTuples($file))->toBe($parityTuples([65]));
});

it('honours the exceptions analogue for one name without silencing the rest', function () use (
    $analyzeOverridden,
    $parityTuples
): void {
    $file = $analyzeOverridden('unused-locals.php', 'validUnusedVariableNames', 'i');

    expect(violationTuples($file))->toBe($parityTuples([23]));
});

it('reports every assignment to an unused name where PHPMD reports one', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals-divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        31 => [UNUSED_VARIABLE],
        32 => [UNUSED_VARIABLE],
    ]);
});
