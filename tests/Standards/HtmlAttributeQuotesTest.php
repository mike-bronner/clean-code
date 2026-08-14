<?php

/**
 * Tests the custom CleanCode.Strings.HtmlAttributeQuotes sniff (Strings:
 * Interpolation, quoting, HereDocs, #25). Fixtures live in
 * tests/fixtures/HtmlAttributeQuotesSniff/ and follow the three-fixture
 * contract.
 *
 * The sniff is isolated from the rest of the master ruleset, which matters
 * more here than usual: failing.php carries a multi-line double-quoted string,
 * and CleanCode.Strings.MultilineStrings would rewrite it to a HEREDOC under
 * the full ruleset. The interaction between the two is pinned in
 * tests/Ruleset/StringsStandardTest.php; this file is about this sniff alone.
 */

declare(strict_types=1);

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

/**
 * Every violation is reported on the string token that carries it, so the
 * column is where the literal opens — column 1 on line 24, which is a
 * *continuation* line of the multi-line string starting on line 23, since
 * PHP_CodeSniffer splits such a string into one token per physical line and
 * the continuation token begins at the start of its line.
 */
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
    ]);
});

/**
 * Six of the seven are fixable. The seventh — line 29's `title='say "hi"'` —
 * has a double quote inside the value, so re-delimiting it is ambiguous and
 * the sniff reports without offering a fix. Pinned as a count so a fixer that
 * started attempting that case would fail here.
 */
it('leaves the ambiguous value unfixable', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(7)
        ->and($file->getFixableCount())->toBe(6);
});

/**
 * The combined-token path: one apostrophe attribute and one already-compliant
 * double-quoted attribute inside a single tag, in a single string token. This
 * drives the per-tag callback wrapping the per-attribute callback, where a
 * regression would most plausibly double-escape or drop the sibling
 * `class="wrap"`. Asserted on the fixer's actual output rather than on the
 * violation count, because the count is identical either way.
 */
it('rewrites only the apostrophe attribute of a combined tag', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$mixed = "<div id=\\"main\\" class=\\"wrap\\">x</div>";');
});

/**
 * The regression this pins: the tag-span pattern used to end at the first
 * literal `>`, so in `<a data-x="a>b" class='y'>` the span stopped inside
 * `data-x` and the trailing `class='y'` was never seen — the string came back
 * from the fixer unchanged. Both halves are asserted: the `>`-bearing value is
 * preserved verbatim, and the attribute after it is converted.
 */
it('does not end a tag span at a greater-than inside an attribute value', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$greaterThanInValue = "<a data-x=\\"a>b\\" class=\\"y\\">link</a>";');
});

/**
 * The apostrophe has to delimit an attribute *inside* a tag to count. Prose,
 * SQL, and body text between tags keep theirs — the false positive that an
 * earlier revision produced by rewriting the whole string rather than its tag
 * spans. All three shapes are in passing.php, so silence there is the
 * assertion.
 */
it('leaves apostrophes outside a tag span alone', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('HtmlAttributeQuotesSniff', 'passing.php')));
});
