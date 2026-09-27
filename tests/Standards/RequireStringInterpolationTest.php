<?php

declare(strict_types=1);

const REQUIRE_STRING_INTERPOLATION = 'CleanCode.Strings.RequireStringInterpolation';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_STRING_INTERPOLATION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 4, 'column' => 24, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 5, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 6, 'column' => 27, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 10, 'column' => 19, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 14, 'column' => 29, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 20, 'column' => 14, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 21, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 22, 'column' => 19, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 23, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 24, 'column' => 37, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 35, 'column' => 23, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 36, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 37, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 44, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 45, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 53, 'column' => 40, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 54, 'column' => 39, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 55, 'column' => 35, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
    ]);
});

it('fixes every chain with an interpolated form, and no others', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect($file->getErrorCount())->toBe(18)
        ->and($file->getFixableCount())->toBe(10);
});

it('refuses to rewrite a literal carrying a binary-string prefix', function (int $line): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(violationSourcesByLine($file->getErrors())[$line] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation']);
})->with([
    'double-quoted' => [36],
    'single-quoted' => [37],
]);

it('leaves a binary-prefixed concatenation byte-for-byte unfixed', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryDouble = B"Total: " . $sum;')
        ->and(autofixedContents($file))
        ->toContain("\$binarySingle = B'Total: ' . \$sum;");
});

it('never rewrites one fragment of a multi-line literal', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('RequireStringInterpolationSniff', 'passing.php')));
});

it('sees through grouping parentheses around an operand', function (int $line): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))
        ->toHaveKey($line)
        ->and(violationSourcesByLine($file->getErrors())[$line])
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation']);
})->with([35, 36, 37]);

it('sees through grouping parentheses a chain hangs off', function (int $twin, int $wrapped): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');
    $sources = violationSourcesByLine($file->getErrors());

    expect($sources[$wrapped] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'])
        ->and($sources[$twin] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.Concatenation']);
})->with([
    'property' => [21, 53],
    'index' => [22, 54],
    'method' => [23, 55],
]);

it('leaves compound parenthesized operands alone', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([]);
});
