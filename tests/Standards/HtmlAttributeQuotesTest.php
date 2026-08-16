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
        ['line' => 35, 'column' => 19, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 46, 'column' => 1, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
        ['line' => 53, 'column' => 21, 'source' => HTML_ATTRIBUTE_QUOTES . '.Apostrophe'],
    ]);
});

/**
 * The regression this pins: the PHP string's own delimiter was read off the
 * token's first character, which for `B'<a class=\'card\'>'` is the
 * binary-string prefix. The literal was then scanned as a double-quoted one,
 * whose attribute apostrophes are bare rather than escaped — so the escaped
 * apostrophes here matched nothing and the violation went unreported. The
 * single-quoted context takes its replacement quotes unescaped, and the prefix
 * survives the rewrite.
 */
it('reads the php delimiter past a binary-string prefix', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryPrefixed = B\'<a class="card">link</a>\';');
});

/**
 * Eight of the ten are fixable. The two held back are line 29's
 * `title='say "hi"'` (a double quote inside the value makes re-delimiting
 * ambiguous) and line 53's `class='card\'` (a backslash in the value would
 * merge with the injected escape). Both report without offering a fix. Pinned
 * as a count so a fixer that started attempting either case would fail here.
 */
it('leaves the unsafe values unfixable', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(10)
        ->and($file->getFixableCount())->toBe(8);
});

/**
 * The regression this pins: the PHP delimiter of a multi-line string was
 * resolved by asking each fragment for its delimiter and taking the first
 * non-null answer. A fragment holds body text, and body text can open with the
 * characters a delimiter is read from — `B'day` on line 45 reads as a
 * binary-string prefix plus an apostrophe. That fragment answered for the whole
 * literal, so the double-quoted string was scanned with the single-quoted
 * escaping convention (`\'` rather than a bare `'`), the attribute on line 46
 * matched nothing, and the violation was silently dropped.
 *
 * Asserted on the fixer's output rather than the report alone, so the fix has
 * to reach the right escaping convention and not merely report something: under
 * the single-quoted convention the replacement quotes are emitted bare, which
 * would break this double-quoted string.
 */
it('reads a multi-line string delimiter past prose that mimics a literal', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('<a class=\\"card\\">link</a>";');
});

/**
 * The regression this pins: the attribute value is captured out of raw PHP
 * source, where a double-quoted string's `\'` is not an escape sequence — both
 * characters survive — so the capture could end on a backslash. The fixer then
 * appended the `\"` closer directly to it, and the two backslashes paired:
 * `"<a class='card\'>link</a>"` was rewritten to `"<a class=\"card\\">link</a>"`,
 * one literal backslash followed by a bare quote that ends the string early.
 * `php -l` rejects the result. The sniff now declines the fix and reports for
 * manual conversion, matching what the sibling fixers in this standard do with
 * a backslash.
 *
 * Both halves are asserted: the violation is still reported (declining a fix
 * must not become silence), and the source comes back from the fixer byte for
 * byte unchanged.
 */
it('declines to fix an attribute value carrying a backslash', function (): void {
    $file = analyzeFixture(HTML_ATTRIBUTE_QUOTES, 'failing.php');

    expect(violationMessagesByLine($file->getErrors())[53][0])
        ->toContain('double quote or a backslash')
        ->and(autofixedContents($file))
        ->toContain('$backslashInValue = "<a class=\'card\\\'>link</a>";');
});

/**
 * A documented limitation, pinned so it cannot change unnoticed.
 *
 * `Markup::tagSpanPattern()` steps over a quoted attribute value as one unit,
 * which needs the apostrophes inside a tag to pair up. An odd number of them
 * leaves the span unmatchable, no span is found, and the sniff stays silent
 * rather than guess where the attribute boundaries are — the same failure mode
 * as a tag with an unterminated quote.
 *
 * The variable is the apostrophe count, not the PHP string context: the odd
 * case is missed in both a double-quoted and a single-quoted PHP string, and
 * the even case is reported in both. All four are asserted together so that
 * reading holds as a pair of controls rather than an assumption.
 */
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
