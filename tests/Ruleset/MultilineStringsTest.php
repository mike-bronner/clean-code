<?php

/**
 * Integration test for the custom CleanCode.Strings.MultilineStrings sniff as
 * wired into the master rules.xml (Code Style: Multiline Strings (HEREDOC),
 * issue #53). Fixtures live in tests/fixtures/MultilineStringsSniff/.
 *
 * Two shapes are covered: a quoted string literal spanning multiple lines
 * (QuotedString, auto-fixed to HEREDOC/NOWDOC) and a multi-line concatenation
 * of quoted strings (Concatenation, detection-only). The fixer assertions prove
 * the conversion is byte-for-byte value-preserving.
 */

declare(strict_types=1);

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

/**
 * Double-quoted, interpolated, escaped-quote, single-quoted, escaped
 * single-quote, and binary-prefixed strings — each reported once, on its first
 * line. $escapes and $literal exercise the fixer's generic escape passthrough
 * (\t, \\, \$ in a double-quoted string → HEREDOC body; literal \n, \t in a
 * single-quoted string → NOWDOC body), and the last two the uppercase
 * binary-string prefix on each delimiter.
 */
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

/**
 * Lines 14 and 16 are the two chains of a single ternary statement: both must
 * report. Keying the dedup on findStartOfStatement() (which does not treat
 * ?/: as boundaries) would drop the second chain.
 */
it('flags multi-line concatenation once at its first string operand', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'concatenation.php');

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 8, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 7, 'column' => 12, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 14, 'column' => 7, 'source' => MULTILINE_STRINGS . '.Concatenation'],
        ['line' => 16, 'column' => 7, 'source' => MULTILINE_STRINGS . '.Concatenation'],
    ]);
});

it('converts multi-line strings to doc syntax when fixed', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'autofixed.php')));
});

it('produces no violations on the autofixed fixture', function (): void {
    expect(violationTuples(analyzeFixture(MULTILINE_STRINGS, 'autofixed.php')))->toBe([]);
});

/**
 * The strongest behaviour-preservation guard: executing the before and after
 * fixtures must yield byte-identical variable values. A fixer that mangled
 * escaping, chose NOWDOC where interpolation was needed, or dropped a
 * character would make these diverge.
 */
it('preserves string values exactly when fixing', function (): void {
    expect(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'failing.php')))
        ->toBe(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'autofixed.php')));
});

it('does not mark concatenation violations auto-fixable', function (): void {
    $flags = violationFixableFlags(analyzeFixture(MULTILINE_STRINGS, 'concatenation.php'));

    expect($flags)->not->toBeEmpty()
        ->and($flags)->each->toBeFalse();
});

/**
 * When a body line would collide with the closing marker, the fix is withheld
 * (buildDocString returns null): the violation is still reported, but as a
 * plain — non-fixable — error, because emitting the HEREDOC would place the
 * marker inside the body and close the doc early. The fixer must leave such a
 * file byte-for-byte unchanged.
 */
it('reports a marker collision as non-fixable and leaves the file untouched', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'marker-collision.php');

    expect(violationTuples($file))
        ->toBe([['line' => 7, 'column' => 8, 'source' => MULTILINE_STRINGS . '.QuotedString']])
        ->and(violationFixableFlags($file))->each->toBeFalse()
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'marker-collision.php')));
});

/**
 * The uppercase binary-string prefix, which stays inside the literal's token
 * where a lowercase `b` becomes a token of its own. buildDocString() read the
 * delimiter off the token's first character, so `B` never matched `'`: a
 * single-quoted literal took the interpolating HEREDOC branch, lost its prefix,
 * and kept its own opening quote in the body.
 *
 * Asserted on the *values*, because the shape assertions above pass either way
 * — the fix produced a well-formed HEREDOC that simply meant something else.
 *
 * The comparison is against the fixer's own output rather than against
 * autofixed.php, and that distinction is the whole test. Every other value
 * assertion in this file evaluates the two committed fixtures, so it measures
 * whether the fixture *pair* agrees and would hold just as well with the fixer
 * broken. Running the fixer here is what makes reverting buildDocString() to
 * `$raw[0]` turn this red.
 */
it('preserves a binary-prefixed multi-line literal exactly', function (string $variable): void {
    $fixed = stageSourceOutsideTests(
        autofixedContents(analyzeFixture(MULTILINE_STRINGS, 'failing.php')),
        'binary-prefixed.php'
    );

    expect(evaluateFixtureVariables($fixed)[$variable])
        ->toBe(evaluateFixtureVariables(fixturePath('MultilineStringsSniff', 'failing.php'))[$variable]);
})->with(['binarySingle', 'binaryDouble']);

/**
 * The same defect class one step further out: PHP_CodeSniffer cannot tokenize
 * an *interpolated* binary-prefixed string at all. It types the `B"` opener
 * T_NONE and mis-types the remainder of the statement, so the sniff was handed
 * a "literal" made of the string's closing quote plus the source that followed
 * it, and rewrote that live source into a HEREDOC.
 *
 * The byte-for-byte comparison is what discriminates here: the mangled output
 * still passed `php -l`, so a parse check would have called it clean. Reverting
 * opensLiteral()'s T_ENCAPSED_AND_WHITESPACE guard turns exactly this red.
 */
it('never rewrites a string the tokenizer could not resolve', function (): void {
    $file = analyzeFixture(MULTILINE_STRINGS, 'unresolved-binary-string.php');

    expect(violationTuples($file))->toBe([])
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultilineStringsSniff', 'unresolved-binary-string.php')));
});
