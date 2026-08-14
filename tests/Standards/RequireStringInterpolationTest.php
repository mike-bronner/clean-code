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
        ['line' => 17, 'column' => 14, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 18, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 19, 'column' => 19, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 20, 'column' => 22, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 21, 'column' => 37, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 27, 'column' => 23, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 28, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 29, 'column' => 31, 'source' => REQUIRE_STRING_INTERPOLATION . '.ComplexConcatenation'],
        ['line' => 36, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
        ['line' => 37, 'column' => 28, 'source' => REQUIRE_STRING_INTERPOLATION . '.Concatenation'],
    ]);
});

/**
 * Only the seven direct two-operand cases are fixable; the eight detection-only
 * ones are not. Asserted as a count rather than a boolean so a fixer that
 * started claiming the complex cases would fail here rather than silently
 * widening its reach.
 */
it('marks only the direct two-operand cases fixable', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getFixableCount())->toBe(7);
});

/**
 * The regression this pins: the literal's delimiter was read off the token's
 * first character, which for `B"Total: "` is the binary-string prefix rather
 * than the quote. The literal took the single-quoted branch, whose job is to
 * escape double quotes for their new context — so it escaped the literal's own
 * opening quote into the value, and `B"Total: " . $sum` was fixed to
 * `"\"Total: {$sum}"`: a different string at runtime.
 *
 * Asserted on the fixer's output, because the violation was reported correctly
 * either way. Both delimiters are covered under that prefix: the double-quoted
 * literal is the one the defect corrupted, and the single-quoted one pins that
 * the prefix is carried over rather than dropped on the branch that already
 * re-encoded its inner text. Only the uppercase spelling can exercise either —
 * PHP_CodeSniffer splits a lowercase `b` off into its own token and leaves an
 * uppercase `B` inside the literal's content.
 */
it('keeps a binary-string prefix out of the interpolated value', function (): void {
    $file = analyzeFixture(REQUIRE_STRING_INTERPOLATION, 'failing.php');

    expect(autofixedContents($file))
        ->toContain('$binaryDouble = B"Total: {$sum}";')
        ->and(autofixedContents($file))
        ->toContain('$binarySingle = B"Total: {$sum}";');
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
})->with([27, 28, 29]);

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
