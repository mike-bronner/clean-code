<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const HTML_ATTRIBUTE_QUOTES = 'CleanCode.Strings.HtmlAttributeQuotes';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(HTML_ATTRIBUTE_QUOTES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 11, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 4, 'column' => 10, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 11, 'column' => 10, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 15, 'column' => 23, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 19, 'column' => 20, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 24, 'column' => 1, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 29, 'column' => 15, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 35, 'column' => 19, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 46, 'column' => 1, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 53, 'column' => 21, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
    ]);
});

it('reads the php delimiter past a binary-string prefix', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryPrefixed = B\'<a class="card">link</a>\';');
});

it('leaves the unsafe values unfixable', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(10)
        ->and($file->getFixableCount())->toBe(8);
});

it('reads a multi-line string delimiter past prose that mimics a literal', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('<a class=\\"card\\">link</a>";');
});

it('declines to fix an attribute value carrying a backslash', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(violationMessagesByLine($file->getErrors())[53][0])
        ->toContain('double quote or a backslash')
        ->and(autofixedContents($file))
        ->toContain('$backslashInValue = "<a class=\'card\\\'>link</a>";');
});

it('stays silent on a tag whose apostrophes do not pair, in either php context', function (): void {
    $analyze = static fn (string $literal): int => analyzeStdinSource(
            [HTML_ATTRIBUTE_QUOTES],
            "<?php\n\n\$x = {$literal};\n"
        )->getErrorCount();

    expect($analyze('"<a class=\'card\'s\'>text</a>"'))->toBe(0)
        ->and($analyze('\'<a class=\\\'card\\\'s\\\'>text</a>\''))->toBe(0)
        ->and($analyze('"<a class=\'card\'>text</a>"'))->toBe(1)
        ->and($analyze('\'<a class=\\\'card\\\'>text</a>\''))->toBe(1);
});

it('rewrites only the apostrophe attribute of a combined tag', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$mixed = "<div id=\\"main\\" class=\\"wrap\\">x</div>";');
});

it('does not end a tag span at a greater-than inside an attribute value', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$greaterThanInValue = "<a data-x=\\"a>b\\" class=\\"y\\">link</a>";');
});

it('leaves apostrophes outside a tag span alone', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('HtmlAttributeQuotesSniff', 'passing.php')));
});

it('treats the whole rewrite as unsafe when an attribute list cannot be read', function (): void {
    $path = fixturePath('HtmlAttributeQuotesSniff', 'failing.php');

    [[$fixed, $messages], $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_replace_callback',
                static function (): array {
                    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

                    return [autofixedContents($file), violationMessagesByLine($file->getErrors())];
                },
                static fn (string $pattern): bool => str_starts_with($pattern, '#([a-zA-Z_:]')
            );
    });

    $reported = array_values(array_unique(array_merge(...array_values($messages))));

    expect($fixed)->toBe(file_get_contents($path))
        ->and($messages)->not->toBe([])
        ->and($reported)->toBe([
            'HTML attributes must use double quotes, not apostrophes; the value contains a'
                . ' double quote or a backslash, so convert this attribute manually',
        ])
        ->and($diagnostics)->toBe([]);
});
