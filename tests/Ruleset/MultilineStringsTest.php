<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const MULTILINE_STRINGS = 'CleanCode.Strings.MultilineStrings';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTILINE_STRINGS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags multi-line string literals at their opening quote', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 5, 'column' => 7, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 8, 'column' => 11, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 11, 'column' => 10, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 14, 'column' => 7, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 17, 'column' => 8, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 20, 'column' => 12, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 24, 'column' => 12, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 32, 'column' => 17, 'source' => MULTILINE_STRINGS . '.QuotedString'],
        ['line' => 38, 'column' => 17, 'source' => MULTILINE_STRINGS . '.QuotedString'],
    ]);
});

it('flags multi-line concatenation once at its first string operand', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'concatenation.php');

    expect(violationTuples($file))->toBe([
        ['line' => 25, 'column' => 14, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 32, 'column' => 18, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 38, 'column' => 7, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 42, 'column' => 7, 'source' => MULTILINE_STRINGS . '.Concatenation'],
    ]);
});

it('stays silent on a sentence wrapped across more source lines than the limit', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'concatenation.php');

    expect(violationSourcesByLine($file->getErrors()))->not->toHaveKey(12);
});

it('converts multi-line strings to doc syntax when fixed', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'autofixed.php')));
});

it('produces no violations on the autofixed fixture', function (): void {
    expect(violationTuples(analyzeFixture(MULTILINE_STRINGS, 'autofixed.php')))->toBe([]);
});

it('preserves string values exactly when fixing', function (): void {
    expect(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'autofixed.php')));
});

it('does not mark concatenation violations auto-fixable', function (): void {
    $flags = violationFixableFlags(analyzeFixture(MULTILINE_STRINGS, 'concatenation.php'));

    expect($flags)->not->toBeEmpty()
        ->and($flags)->each->toBeFalse();
});

it('reports a marker collision as non-fixable and leaves the file untouched', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'marker-collision.php');

    expect(violationTuples($file))
        ->toBe([['line' => 7, 'column' => 8, 'source' => MULTILINE_STRINGS . '.QuotedString']])
        ->and(violationFixableFlags($file))->each->toBeFalse()
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'marker-collision.php')));
});

it('preserves a binary-prefixed multi-line literal exactly', function (string $variable): void {
    $fixed = stageSourceOutsideTests(
            autofixedContents(analyzeFixture(MULTILINE_STRINGS, 'failing.php')),
            'binary-prefixed.php'
        );

    expect(evaluateFixtureVariables($fixed)[$variable])
        ->toBe(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'failing.php'))[$variable]);
})->with(['binarySingle', 'binaryDouble']);

it('never rewrites a string the tokenizer could not resolve', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'unresolved-binary-string.php');

    expect(violationTuples($file))->toBe([])
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'unresolved-binary-string.php')));
});

it('rewrites nothing when a string body cannot be split into lines', function (): void {
    $path = fixturePath('MultilineStringsSniff', 'failing.php');

    [$fixed, $diagnostics] = withPhpDiagnostics(static function (): string {
        return PregFailure::during(
                'preg_split',
                static fn (): string => autofixedContents(analyzeFixture(MULTILINE_STRINGS, 'failing.php')),
                static fn (string $pattern): bool => $pattern === '/\r\n|\n|\r/'
            );
    });

    expect($fixed)->toBe(file_get_contents($path))
        ->and($diagnostics)->toBe([]);
});
