<?php

/**
 * Tests the custom CleanCode.WhiteSpace.PassiveOperatorSpacing sniff
 * (Operators: Passive, #64). Fixtures live in
 * tests/fixtures/PassiveOperatorSpacingSniff/ and follow the three-fixture
 * contract: passing.php is clean, failing.php carries every violation code,
 * and autofixed.php is phpcbf's output for failing.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml. What the sniff does *alongside* the
 * rest of the ruleset — the part that decides whether phpcbf converges — is
 * pinned separately by tests/Ruleset/OperatorsPassiveTest.php.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const PASSIVE_OPERATOR_SPACING = 'CleanCode.WhiteSpace.PassiveOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PASSIVE_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The whole contract: an exact line, column and code for every report, so a
 * violation that moves, doubles or changes code fails the assertion.
 *
 * Line 13 carries two Execution reports because an interpolated command is
 * several tokens, so the leading and trailing trims are separate fixes — and
 * the two columns are what tells them apart. Lines 24 and 25 carry two reports
 * each — the `@` and the sign it suppresses are both spaced, and both are this
 * standard's operators — each at its own operator's column.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 10, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 11, 'column' => 19, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 12, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 13, 'column' => 21, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 13, 'column' => 31, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 14, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 15, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 24, 'column' => 27, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 24, 'column' => 29, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 25, 'column' => 27, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 25, 'column' => 29, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 35, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 38, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(14)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/../fixtures/PassiveOperatorSpacingSniff/autofixed.php')
    );
});

/**
 * Signs that open a statement (line 35) and a PHP block (line 38) are the two
 * contexts this standard reclaimed from the binary-operator sniff, so they get
 * their own assertion rather than only riding along in the map above: if
 * CleanCode.Operators.BinaryOperatorSpacing stops ceding them, this sniff must
 * still be the one reporting them.
 */
it('owns a sign that opens a statement or a PHP block', function (): void {
    $tuples = violationTuples(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php'));

    $atLine = static fn (int $line): array => array_values(array_filter(
        $tuples,
        static fn (array $violation): bool => $violation['line'] === $line
    ));

    expect($atLine(35))
        ->toBe([['line' => 35, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']])
        ->and($atLine(38))
        ->toBe([['line' => 38, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']]);
});

/**
 * The guard exists to stop the fixer changing what the code means: `- -$a`
 * would fuse into the pre-decrement `--$a`. It is deliberately
 * direction-matched — `- ++$a` fuses into nothing and *is* reported (line 14
 * of the failing fixture) — so this asserts the narrow half stays narrow.
 */
it('leaves a same-direction sign pair untouched', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'guarded.php');
    $path = __DIR__ . '/../fixtures/PassiveOperatorSpacingSniff/guarded.php';

    // Silence *and* byte-identity: the guard withholds the fix as well as the
    // report, so a guard that only stopped reporting would still fail here.
    expect($file->getErrorCount())->toBe(0)
        ->and(autofixedContents($file))->toBe(file_get_contents($path));
});

/**
 * The direction-matched half of the same guard. `- ++$number` fuses into
 * nothing, so it is reported and fixed like any other spaced sign — broadening
 * the guard to all four sign tokens would silently drop these two.
 */
it('still reports a cross-direction sign pair', function (): void {
    $tuples = violationTuples(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php'));

    $atLine = static fn (int $line): array => array_values(array_filter(
        $tuples,
        static fn (array $violation): bool => $violation['line'] === $line
    ));

    expect($atLine(14))
        ->toBe([['line' => 14, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']])
        ->and($atLine(15))
        ->toBe([['line' => 15, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity']]);
});

/**
 * A spaced *binary* `+`/`-` is required by the binary-operator standard (#35).
 * If this sniff ever reported one, the two standards would contradict each
 * other and no source file could satisfy both.
 */
it('never reports a binary sign', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrorCount())->toBe(0);
});

/**
 * The backtick fixer compares a trimmed copy of the content against the
 * original to decide whether there is anything to trim. A failed read is null,
 * and `null === $content` is false — so the failure would fall past that guard
 * and hand null to the fixer as the replacement text, emptying the backticks.
 *
 * The content itself is the guard's fallback: it reads as "nothing to trim",
 * so the backtick content is left as written and the violation that depended on
 * the trim goes unreported. That is what is asserted — not that the fixer
 * leaves the whole file alone, which it does not: this sniff's other reports on
 * the same fixture are untouched by the failed read and still get fixed.
 */
it('leaves backtick content alone when it cannot be trimmed', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => str_contains($pattern, '[ \t]+')
        );
    });

    expect(array_keys($expected))->toContain(12)
        ->and(array_keys($degraded))->not->toContain(12)
        ->and($diagnostics)->toBe([]);
});
