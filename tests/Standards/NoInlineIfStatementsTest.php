<?php

declare(strict_types=1);

const INLINE_CONTROL_STRUCTURE = 'Generic.ControlStructures.InlineControlStructure';

it('is reachable through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(INLINE_CONTROL_STRUCTURE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags inline conditionals at the expected lines', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => INLINE_CONTROL_STRUCTURE . '.NotAllowed',
        ],
        [
            [25, 1], [28, 1], [29, 1], [32, 1], [33, 1], [34, 1],
            [37, 1], [37, 22], [41, 5], [46, 5],
        ]
    ));
});

it('marks every violation auto-fixable', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('adds braces when fixed', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('InlineControlStructureSniff', 'autofixed.php')));
});
