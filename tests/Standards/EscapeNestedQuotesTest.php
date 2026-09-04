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
        ['line' => 14, 'column' => 15, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 18, 'column' => 14, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 23, 'column' => 19, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
        ['line' => 31, 'column' => 23, 'source' => ESCAPE_NESTED_QUOTES . '.UnescapedQuote'],
    ]);
});

/**
 * The regression this pins: the sniff decided a literal was single-quoted by
 * reading its first character, which for `B'He said "hi"'` is the
 * binary-string prefix — so the literal was skipped outright and the rule went
 * unenforced on it. PHP_CodeSniffer splits a lowercase `b` off into its own
 * token but keeps an uppercase `B` inside the literal's content, so the
 * uppercase spelling is the one that hid the violation. The fixer carries the
 * prefix over rather than dropping it.
 */
it('reads the delimiter past a binary-string prefix', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryPrefixed = B"He said \\"hi\\" to me";');
});

/**
 * What makes carrying that prefix safe: this fixer's output never interpolates.
 * PHP_CodeSniffer reads `B"He said \"hi\""` as a plain
 * T_CONSTANT_ENCAPSED_STRING, but types the opener of a prefixed *interpolating*
 * literal (`B"echo \"$value\" here"`) T_NONE and swallows the source after it,
 * which silently hides every later violation in the file.
 *
 * The property used to be held by refusing `$` outright. It is now held by
 * escaping it: `$` leaves as `\$`, so no output of this fixer interpolates, and
 * the prefix stays safe to carry. That is the coupling this pins — a fixer that
 * emitted a bare `$` would corrupt the tokenizer, so the escaped form is
 * asserted directly rather than inferred from the fixable flags.
 */
it('never carries a prefix onto output that would interpolate', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect(autofixedContents($file))
        ->toContain("\$binaryWithVariable = B\"echo \\\"\\\$value\\\" here\";")
        ->not->toContain("B\"echo \\\"\$value");
});

/**
 * A literal whose source spans several physical lines is tokenized one token
 * per line. Only the first fragment opens with the delimiter, so the sniff used
 * to accept it as a whole literal and re-delimit that fragment alone — leaving
 * the string unterminated and the file unparseable. passing.php carries such a
 * literal with double quotes in its value, so silence there is the assertion,
 * and the fixer must return it byte-for-byte.
 */
it('never re-delimits one fragment of a multi-line literal', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('EscapeNestedQuotesSniff', 'passing.php')));
});

/**
 * Every literal is fixable: a single-quoted body resolves only `\\` and `\'`,
 * so both come back to the characters they stand for before the whole thing is
 * escaped for a double-quoted body. There is no single-quoted literal this
 * cannot carry across.
 */
it('fixes every literal it reports', function (): void {
    $file = analyzeFixture(ESCAPE_NESTED_QUOTES, 'failing.php');

    expect($file->getErrorCount())->toBe(7)
        ->and($file->getFixableCount())->toBe(7)
        ->and(violationFixableFlags($file))->toBe([true, true, true, true, true, true, true]);
});

/**
 * The strongest guard: executing the two committed fixtures and comparing every
 * variable. Escaping is where a re-delimiting fixer goes wrong, and a wrong
 * escape is not a lint finding in the consumer's code — it is a changed string
 * at runtime. Line-level assertions cannot see that; this can.
 */
it('produces a byte-for-byte identical value for every literal', function (): void {
    expect(evaluateFixtureVariables(fixturePath('EscapeNestedQuotesSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('EscapeNestedQuotesSniff', 'autofixed.php')));
});

/**
 * Each trigger that used to force a manual conversion, one at a time, so a
 * regression that lost just one of the three cannot hide behind the other two.
 * Every case must report and every case must fix.
 */
it('fixes each interpolation trigger rather than refusing it', function (string $literal): void {
    $file = analyzeStdinSource([ESCAPE_NESTED_QUOTES], "<?php\n\n\$x = {$literal};\n");

    expect($file->getErrorCount())->toBe(1)
        ->and($file->getFixableCount())->toBe(1);
})->with([
    'dollar' => "'say \"\$value\" now'",
    'brace' => "'say \"{name}\" now'",
    'backslash' => "'say \"C:\\\\temp\" now'",
]);
