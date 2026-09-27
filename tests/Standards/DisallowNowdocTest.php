<?php

declare(strict_types=1);

const DISALLOW_NOWDOC = 'CleanCode.Strings.DisallowNowdoc';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_NOWDOC);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_NOWDOC, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(DISALLOW_NOWDOC, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 10, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
        ['line' => 20, 'column' => 11, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
        ['line' => 27, 'column' => 11, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
        ['line' => 33, 'column' => 14, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
        ['line' => 40, 'column' => 19, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
        ['line' => 46, 'column' => 11, 'source' => DISALLOW_NOWDOC . '.NowdocFound'],
    ]);
});

it('fixes every NOWDOC it reports', function (): void {
    $file = analyzeFixture(DISALLOW_NOWDOC, 'failing.php');

    expect($file->getErrorCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(6)
        ->and(violationFixableFlags($file))->toBe([true, true, true, true, true, true]);
});

it('produces a byte-for-byte identical value for every body', function (): void {
    expect(evaluateFixtureVariables(fixturePath('DisallowNowdocSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('DisallowNowdocSniff', 'autofixed.php')));
});

it('escapes every character a HEREDOC body resolves', function (string $body, string $expected): void {
    $path = stageSource("<?php\n\n\$x = <<<'TEXT'\n{$body}\nTEXT;\n", 'nowdoc.php');
    $file = analyzeWithSniffs([DISALLOW_NOWDOC], $path);

    expect($file->getErrorCount())->toBe(1)
        ->and(autofixedContents($file))->toContain($expected);
})->with([
    'bare dollar' => ['a $name here', 'a \\$name here'],
    'brace trigger' => ['a {$name} here', 'a {\\$name} here'],
    'dollar-brace trigger' => ['a ${name} here', 'a \\${name} here'],
    'backslash' => ['C:\\temp', 'C:\\\\temp'],
    'escape sequence' => ['tab is \\t', 'tab is \\\\t'],
]);

it('leaves a double quote alone', function (): void {
    $path = stageSource("<?php\n\n\$x = <<<'TEXT'\nhe said \"hi\"\nTEXT;\n", 'nowdoc.php');
    $file = analyzeWithSniffs([DISALLOW_NOWDOC], $path);

    expect(autofixedContents($file))->toContain('he said "hi"')
        ->and(autofixedContents($file))->not->toContain('\\"');
});
