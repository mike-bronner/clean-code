<?php

declare(strict_types=1);

const ESCAPE_NESTED_QUOTES = 'CleanCode.Strings.EscapeNestedQuotes';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ESCAPE_NESTED_QUOTES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 4, 'column' => 11, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 5, 'column' => 14, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 9, 'column' => 17, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 14, 'column' => 15, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 18, 'column' => 14, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 23, 'column' => 19, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 31, 'column' => 23, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
    ]);
});

it('reads the delimiter past a binary-string prefix', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryPrefixed = B"He said \\"hi\\" to me";');
});

it('never carries a prefix onto output that would interpolate', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain("\$binaryWithVariable = B\"echo \\\"\\\$value\\\" here\";")
        ->not->toContain("B\"echo \\\"\$value");
});

it('never re-delimits one fragment of a multi-line literal', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('EscapeNestedQuotesSniff', 'passing.php')));
});

it('fixes every literal it reports', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(7)
        ->and($file->getFixableCount())->toBe(7)
        ->and(violationFixableFlags($file))->toBe([true, true, true, true, true, true, true]);
});

it('produces a byte-for-byte identical value for every literal', function (): void {
    expect(evaluateFixtureVariables(fixturePath('EscapeNestedQuotesSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('EscapeNestedQuotesSniff', 'autofixed.php')));
});

it('fixes each interpolation trigger rather than refusing it', function (string $literal): void {
    $file = analyzeStdinSource([ESCAPE_NESTED_QUOTES], "<?php\n\n\$x = {$literal};\n");

    expect($file->getErrorCount())->toBe(1)
        ->and($file->getFixableCount())->toBe(1);
})->with([
    'dollar' => "'say \"\$value\" now'",
    'brace' => "'say \"{name}\" now'",
    'backslash' => "'say \"C:\\\\temp\" now'",
]);
