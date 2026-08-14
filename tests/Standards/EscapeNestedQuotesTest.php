<?php

/**
 * Tests the custom CleanCode.Strings.EscapeNestedQuotes sniff (Strings:
 * Interpolation, quoting, HereDocs, #25). Fixtures live in
 * tests/fixtures/EscapeNestedQuotesSniff/ and follow the three-fixture
 * contract.
 *
 * On what this sniff enforces: the acceptance criterion asks for "unescaped
 * quotes nested inside a string that uses the same quote character", which PHP
 * cannot tokenize at all — an unescaped same-delimiter quote ends the string,
 * so `'it's'` is a parse error, not a lintable shape. The reachable form of
 * the rule is the dodge people reach for instead: switching the whole literal
 * to single quotes to avoid escaping an inner double quote. That is what is
 * flagged here, and the ruling stands unchallenged from review.
 */

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

/**
 * Line *and column* for all five violations. The column is where the literal
 * opens, counted by hand against the fixture: line 16 is
 * `$withBrace = 'render "{name}" now';` — 10 characters of variable name,
 * space, `=`, space — so the `'` is column 14.
 */
it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 4, 'column' => 11, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 5, 'column' => 14, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 9, 'column' => 17, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 13, 'column' => 15, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 16, 'column' => 14, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
    ]);
});

/**
 * Only the two literals that carry no interpolation trigger and no backslash
 * escape are fixable; the `$`, `\` and `{` cases are reported for manual
 * conversion. A fixer that widened to any of those three would change the
 * string's meaning, so the split is pinned as a count.
 */
it('marks only the meaning-preserving conversions fixable', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe(2);
});

/**
 * Each unsafe-to-convert trigger, one at a time, so a regression that dropped
 * just one of the three from isSafeToConvert() cannot hide behind the other
 * two. Every case must report, and none of them may be fixable.
 */
it('reports but never fixes a literal it cannot re-delimit safely', function (string $literal): void {
    $file = analyzeStdinSource([ESCAPE_NESTED_QUOTES], "<?php\n\n\$x = {$literal};\n");

    expect($file->getErrorCount())->toBe(1)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    'dollar' => "'say \"\$value\" now'",
    'brace' => "'say \"{name}\" now'",
    'backslash' => "'say \"C:\\\\temp\" now'",
]);
