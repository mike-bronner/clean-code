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
 * Double-quoted, interpolated, escaped-quote, single-quoted, and escaped
 * single-quote strings — each reported once, on its first line. The last two
 * exercise the fixer's generic escape passthrough: $escapes (\t, \\, \$ in a
 * double-quoted string → HEREDOC body) and $literal (literal \n, \t in a
 * single-quoted string → NOWDOC body).
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
