<?php

/**
 * Tests the custom CleanCode.Strings.DisallowNowdoc sniff (Strings:
 * Interpolation, quoting, HereDocs, #25). Fixtures live in
 * tests/fixtures/DisallowNowdocSniff/ and follow the three-fixture contract.
 *
 * On what this sniff enforces: a NOWDOC and a HEREDOC differ only in the quotes
 * around the opening identifier, and that one character changes how every later
 * reader has to think about the body. Carrying both forms means a reader checks
 * the delimiter before trusting what the body says, so the package keeps one —
 * the HEREDOC, which is what every other fixer here emits.
 *
 * Nothing is lost in the conversion, and that is the claim the tests below
 * actually verify rather than assert: a HEREDOC body carries any literal text a
 * NOWDOC can, with a backslash and a `$` escaped.
 */

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

/**
 * Line *and column* for all six violations, which pins the report to the
 * opening `<<<` rather than to the body or the closing marker. The column is
 * where the opener starts: line 13 is `$plain = <<<'TEXT'`, so 6 characters of
 * variable name, space, `=`, space puts the `<` at column 10.
 */
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

/**
 * Every NOWDOC is fixable, with no shape held back. A HEREDOC body resolves
 * exactly two things a NOWDOC body does not — a backslash escape and an
 * interpolation — and escaping the backslash and the `$` covers both. There is
 * no NOWDOC this cannot carry across.
 */
it('fixes every NOWDOC it reports', function (): void {
    $file = analyzeFixture(DISALLOW_NOWDOC, 'failing.php');

    expect($file->getErrorCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(6)
        ->and(violationFixableFlags($file))->toBe([true, true, true, true, true, true]);
});

/**
 * The strongest guard: executing the two committed fixtures and comparing every
 * variable. Escaping is where a body-rewriting fixer goes wrong, and a wrong
 * escape is not a lint finding in the consumer's code — it is a changed string
 * at runtime. A fixer that only stripped the identifier's quotes would pass
 * every line-level assertion above and still turn `\n` into a newline and
 * `$value` into whatever the variable holds. Line-level assertions cannot see
 * that; this can.
 */
it('produces a byte-for-byte identical value for every body', function (): void {
    expect(evaluateFixtureVariables(fixturePath('DisallowNowdocSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('DisallowNowdocSniff', 'autofixed.php')));
});

/**
 * Each escape the conversion has to add, one at a time, so a regression that
 * lost just one of them cannot hide behind the others. The `$` cases cover both
 * brace triggers, because escaping the dollar is what neutralises `{$` and `${`
 * alike.
 */
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

/**
 * What needs *no* escape, asserted directly rather than left to the value
 * comparison. A `"` is the character both quoted forms make a reader escape,
 * and a HEREDOC carries it as-is — so a fixer that escaped it defensively would
 * still produce an identical value and would still be wrong about the output
 * this standard asks for.
 */
it('leaves a double quote alone', function (): void {
    $path = stageSource("<?php\n\n\$x = <<<'TEXT'\nhe said \"hi\"\nTEXT;\n", 'nowdoc.php');
    $file = analyzeWithSniffs([DISALLOW_NOWDOC], $path);

    expect(autofixedContents($file))->toContain('he said "hi"')
        ->and(autofixedContents($file))->not->toContain('\\"');
});
