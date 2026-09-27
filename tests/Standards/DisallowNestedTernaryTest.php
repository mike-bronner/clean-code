<?php

declare(strict_types=1);

const NESTED_TERNARY_SNIFF = 'CleanCode.Conditionals.DisallowNestedTernary';

const NESTED_TERNARY = NESTED_TERNARY_SNIFF . '.NestedTernary';

const NESTED_TERNARY_VIOLATIONS = [
    ['line' => 4, 'column' => 34, 'source' => NESTED_TERNARY],
    ['line' => 7, 'column' => 36, 'source' => NESTED_TERNARY],
    ['line' => 10, 'column' => 16, 'source' => NESTED_TERNARY],
    ['line' => 13, 'column' => 32, 'source' => NESTED_TERNARY],
    ['line' => 17, 'column' => 18, 'source' => NESTED_TERNARY],
    ['line' => 23, 'column' => 34, 'source' => NESTED_TERNARY],
    ['line' => 26, 'column' => 33, 'source' => NESTED_TERNARY],
    ['line' => 30, 'column' => 17, 'source' => NESTED_TERNARY],
    ['line' => 31, 'column' => 16, 'source' => NESTED_TERNARY],
    ['line' => 32, 'column' => 23, 'source' => NESTED_TERNARY],
    ['line' => 36, 'column' => 38, 'source' => NESTED_TERNARY],
    ['line' => 42, 'column' => 42, 'source' => NESTED_TERNARY],
    ['line' => 48, 'column' => 30, 'source' => NESTED_TERNARY],
    ['line' => 53, 'column' => 28, 'source' => NESTED_TERNARY],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NESTED_TERNARY_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports every nesting at the nested operator', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe(NESTED_TERNARY_VIOLATIONS);
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(NESTED_TERNARY_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
