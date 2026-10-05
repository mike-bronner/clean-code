<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const NEW_WITHOUT_PARENTHESES = 'CleanCode.Classes.NewWithoutParentheses';

const CLASS_INSTANTIATION = 'PSR12.Classes.ClassInstantiation';

const SLEVOMAT_NEW_WITHOUT_PARENTHESES = 'SlevomatCodingStandard.ControlStructures.NewWithoutParentheses';

$linesFlaggedBy = static function (LocalFile $file, string $sniffCode): array {
    $lines = [];

    foreach (allViolationSourcesByLine($file) as $line => $sources) {
        foreach ($sources as $source) {
            if (str_starts_with($source, $sniffCode . '.')) {
                $lines[] = $line;
            }
        }
    }

    return array_values(array_unique($lines));
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NEW_WITHOUT_PARENTHESES);
});

it('replaces the PSR-12 rule that demands the parentheses rather than running alongside it', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey(CLASS_INSTANTIATION);
});

it('reports nothing from the PSR-12 rule through the master ruleset', function () use ($linesFlaggedBy): void {
    $file = analyzeWithMasterRuleset(fixturePath('NewWithoutParenthesesSniff', 'passing.php'));

    expect($linesFlaggedBy($file, CLASS_INSTANTIATION))->toBe([]);
});

it('would trip the PSR-12 rule on the compliant fixture without the exclude', function () use ($linesFlaggedBy): void {
    $file = analyzeWithStandard('PSR12', fixturePath('NewWithoutParenthesesSniff', 'passing.php'));

    expect($linesFlaggedBy($file, CLASS_INSTANTIATION))->toBe([12, 13, 14, 15, 22, 27, 28, 29, 30, 31, 34]);
});

it('accepts instantiation without parentheses, with arguments, dereferenced, or anonymous', function (): void {
    $file = analyzeFixture(NEW_WITHOUT_PARENTHESES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags empty parentheses after every form of class reference', function (): void {
    $file = analyzeFixture(NEW_WITHOUT_PARENTHESES, 'failing.php');
    $columns = array_fill_keys(range(12, 32), 13);
    $columns[27] = 14;
    $columns[34] = 18;

    expect(violationTuples($file))->toBe(array_map(
            static fn (int $line, int $column): array => [
                'line' => $line,
                'column' => $column,
                'source' => NEW_WITHOUT_PARENTHESES . '.Found',
            ],
            array_keys($columns),
            $columns
        ));
});

it('removes only the parentheses that belong to the instantiation', function (): void {
    $fixed = autofixedContents(analyzeFixture(NEW_WITHOUT_PARENTHESES, 'failing.php'));

    expect($fixed)->toContain("            new (resolveClass()),\n")
        ->and($fixed)->toContain("            new Invoice == \$other->build(),\n")
        ->and($fixed)->toContain("            new Invoice /* no arguments */,\n")
        ->and($fixed)->toContain("            new Invoice,\n            send(new Invoice),\n");
});

it('exists because the Slevomat sniff misreads where a class reference ends', function () use ($linesFlaggedBy): void {
    $file = analyzeWithStandard(
            'SlevomatCodingStandard',
            fixturePath('NewWithoutParenthesesSniff', 'passing.php')
        );

    expect($linesFlaggedBy($file, SLEVOMAT_NEW_WITHOUT_PARENTHESES))->toBe([32, 34]);
});
