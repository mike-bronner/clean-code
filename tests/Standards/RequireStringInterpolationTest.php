<?php

/**
 * Tests the custom CleanCode.Strings.RequireStringInterpolation sniff
 * (Strings: Interpolation, quoting, HereDocs, #25). Fixtures live in
 * tests/fixtures/RequireStringInterpolationSniff/ and follow the three-fixture
 * contract.
 *
 * The sniff is isolated from the rest of the master ruleset so these
 * assertions stay stable as sibling standards land in rules.xml — in
 * particular CleanCode.Strings.MultilineStrings, which also registers on the
 * string tokens these fixtures are built from.
 */

declare(strict_types=1);

const REQUIRE_STRING_INTERPOLATION = 'CleanCode.Strings.RequireStringInterpolation';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_STRING_INTERPOLATION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Line and column for every violation. The two codes are pinned apart on
 * purpose: Concatenation is the fixable, mechanically-rewritable case, and
 * ComplexConcatenation the detection-only one, so a sniff that quietly
 * promoted one to the other would still satisfy a bare "flags this line".
 */
it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    // Every column is the `.` operator the violation is reported on, counted
    // by hand against the fixture rather than read back off the sniff. Line 4
    // is `$simpleLeft = 'Hello ' . $name;` — 11 characters of variable, space,
    // `=`, space, 8 of literal, space, so the `.` is column 24. Line 29 is
    // `$spacedParenthesized = ( $d ) . 'q';` — 20, space, `=`, space, `(`,
    // space, `$d`, space, `)`, space, so the `.` is column 31.
    expect(violationTuples($file))->toBe([
        ['line' => 4, 'column' => 24, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 5, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 6, 'column' => 27, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 10, 'column' => 19, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 14, 'column' => 29, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 20, 'column' => 14, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 21, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 22, 'column' => 19, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 23, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 24, 'column' => 37, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 35, 'column' => 23, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 36, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 37, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 44, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 45, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 53, 'column' => 40, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 54, 'column' => 39, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 55, 'column' => 35, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
    ]);
});

/**
 * Ten of the eighteen are fixable; the eight that are not are the two shapes
 * with no interpolated form at all — a grouping parenthesis (`{($b)}` is a
 * brace followed by text) and a binary-string prefix. Asserted as a count
 * rather than a boolean so a fixer that started claiming those would fail here
 * rather than silently widening its reach.
 */
it('fixes every chain with an interpolated form, and no others', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect($file->getErrorCount())->toBe(18)
        ->and($file->getFixableCount())->toBe(10);
});

/**
 * The regression this pins: the fixer used to carry a binary-string prefix onto
 * its own interpolated output, emitting `B"Total: {$sum}"`. PHP itself accepts
 * that, so `php -l` stayed silent — but PHP_CodeSniffer's tokenizer does not
 * read it: it types the `B"` opener T_NONE and folds the rest of the statement,
 * and the source *after* it, into one bogus T_DOUBLE_QUOTED_STRING. Every later
 * sniff then reads live code as string body. The previous fixture committed
 * that corrupted output as the expected result.
 *
 * There is no correct fixed form — the result of this fixer always interpolates,
 * and dropping the prefix instead would rest on it being a no-op — so the shape
 * is now refused and reported as detection-only. Only the uppercase spelling can
 * reach the sniff at all: PHP_CodeSniffer splits a lowercase `b` into its own
 * T_BINARY_CAST token and leaves an uppercase `B` inside the literal's content.
 *
 * Asserted on the fixer's own output rather than on the committed autofixed.php,
 * so a fixer that resumed rewriting these fails here.
 */
it('refuses to rewrite a literal carrying a binary-string prefix', function (int $line): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(violationSourcesByLine($file->getErrors())[$line] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation']);
})->with([
    'double-quoted' => [36],
    'single-quoted' => [37],
]);

it('leaves a binary-prefixed concatenation byte-for-byte unfixed', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryDouble = B"Total: " . $sum;')
        ->and(autofixedContents($file))
        ->toContain("\$binarySingle = B'Total: ' . \$sum;");
});

/**
 * A literal whose source spans several physical lines is tokenized one token
 * per line, so no single token holds it. The fixer replaces one token, which
 * on the last fragment of such a literal left `$multi = 'line one` above a
 * rewritten second line — a parse error, produced silently by phpcbf. The
 * shape now reports nothing at all and is left to
 * CleanCode.Strings.MultilineStrings, so the assertion is that passing.php's
 * two multi-line cases come back from the fixer byte-for-byte.
 */
it('never rewrites one fragment of a multi-line literal', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('RequireStringInterpolationSniff', 'passing.php')));
});

/**
 * The regression this pins: `operandStart()` used to step past a bare grouping
 * parenthesis to the token *before* it — the assignment operator — so the
 * operand boundary was wrong and the later interpolatability guard discarded
 * the whole chain. `($b) . 'y'` produced no violation of any kind while the
 * identical `$b . 'y'` was flagged. Parentheses are transparent to the
 * standard, so all three shapes have to report.
 */
it('sees through grouping parentheses around an operand', function (int $line): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))
        ->toHaveKey($line)
        ->and(violationSourcesByLine($file->getErrors())[$line])
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation']);
})->with([35, 36, 37]);

/**
 * The rest of "parentheses are transparent": a member, index, or call chain
 * hanging off the closing parenthesis. operandPointer() bounded its unwrapping
 * walks by the *operand's* end, which such a chain runs past — so the wrapped
 * token never matched the walk from the other side, the operand was classified
 * non-interpolatable, and all three reported nothing at all.
 *
 * Asserted as a pair rather than as "this line reports", because the defect was
 * precisely a disagreement between the two: a sniff that fell silent on the
 * wrapped one fails here where a bare presence check would pass.
 *
 * The two carry *different codes* now, and that is the point rather than a
 * regression. Detection is identical — parentheses are transparent to the
 * standard, so both report. Fixing is not: the twin has an interpolated form
 * and is written, while `{($user)->name}` is a brace followed by text, so the
 * wrapped one stays detection-only.
 */
it('sees through grouping parentheses a chain hangs off', function (int $twin, int $wrapped): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');
    $sources = violationSourcesByLine($file->getErrors());

    expect($sources[$wrapped] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'])
        ->and($sources[$twin] ?? null)
        ->toBe([REQUIRE_STRING_INTERPOLATION . '.Concatenation']);
})->with([
    'property' => [21, 53],
    'index' => [22, 54],
    'method' => [23, 55],
]);

/**
 * The other half of that fix, and the reason the unwrapping is restricted to
 * a single wrapped token. `($count + 1)` opens with a T_VARIABLE exactly as
 * `($b)` does, but `"total: {$count + 1}"` is not valid PHP — unwrapping on
 * the first token alone would report a concatenation with no interpolated
 * form. Both compound shapes live in passing.php, so this asserts the sniff
 * stays silent on precisely them.
 */
it('leaves compound parenthesized operands alone', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'passing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([]);
});
