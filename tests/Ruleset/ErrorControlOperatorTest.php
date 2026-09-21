<?php

/**
 * Integration test for the Generic.PHP.NoSilencedErrors rule as configured in
 * the master CleanCode/ruleset.xml, which replaces PHPMD's CleanCode/ErrorControlOperator
 * rule (issue #82). Fixtures live in tests/fixtures/NoSilencedErrorsSniff/.
 *
 * Two pieces of configuration are pinned here, because both are invisible in
 * the fixtures themselves:
 *
 *   - error="true" is the sniff's own severity switch, and it moves the report
 *     *and* its code: an error under `.Forbidden` instead of a warning under
 *     `.Discouraged`. Dropping the property leaves the sniff detecting exactly
 *     the same operators, so only an assertion naming the code and reading
 *     getErrors() catches the regression. Error severity is what makes phpcs
 *     fail the run the way phpmd does, which is the whole point of the mapping.
 *   - There is no autofixed.php, because the rule is not auto-fixable —
 *     Generic\Sniffs\PHP\NoSilencedErrorsSniff registers no fixer, and
 *     stripping an `@` changes runtime behaviour. The fixable-count test below
 *     pins that, so the absent fixture stays an asserted fact rather than an
 *     assumption.
 */

declare(strict_types=1);

const SILENCED_ERRORS_SNIFF = 'Generic.PHP.NoSilencedErrors';

const SILENCED_ERRORS_CODE = SILENCED_ERRORS_SNIFF . '.Forbidden';

/**
 * Every error-control operator in failing.php, at the line and column it is
 * written on. Line 31 carries two, either side of a concatenation, so the pair
 * also proves the sniff reports per operator rather than per line.
 */
const SILENCED_ERRORS_VIOLATIONS = [
    ['line' => 25, 'column' => 17, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 26, 'column' => 16, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 27, 'column' => 21, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 28, 'column' => 19, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 29, 'column' => 19, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 30, 'column' => 20, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 31, 'column' => 17, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 31, 'column' => 34, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 33, 'column' => 9, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 34, 'column' => 9, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 47, 'column' => 12, 'source' => SILENCED_ERRORS_CODE],
];

/**
 * The file-scope operators in divergences.php: reported by the sniff, and
 * invisible to PHPMD, whose rule is MethodAware/FunctionAware only.
 */
const SILENCED_ERRORS_DIVERGENCES = [
    ['line' => 19, 'column' => 13, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 20, 'column' => 8, 'source' => SILENCED_ERRORS_CODE],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SILENCED_ERRORS_SNIFF);
});

/**
 * passing.php is deliberately full of `@` characters that are not the operator
 * — an address in single-quoted, double-quoted, heredoc and nowdoc strings,
 * docblock tags, an inline comment, and two PHP 8 attributes. The sniff
 * registers on T_ASPERAND, which the tokenizer emits for none of them, and this
 * is the assertion that says so.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each error-control operator at its own line and column', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe(SILENCED_ERRORS_VIOLATIONS);
});

it('reports suppressed errors as errors rather than warnings', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(SILENCED_ERRORS_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and(warningTuples($file))->toBe([]);
});

it('reports suppressed errors without offering an auto-fix', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    // Guard against a vacuous pass: an empty report also has zero fixable
    // violations, so pin that the violations are actually there first.
    expect($file->getErrorCount())->toBe(count(SILENCED_ERRORS_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))
        ->toBe(array_fill(0, count(SILENCED_ERRORS_VIOLATIONS), false));
});

/**
 * The one shape where the sniff is stricter than PHPMD. PHPMD's rule implements
 * MethodAware and FunctionAware, so it is only ever handed a method or function
 * node and never sees an `@` at a file's top level; the sniff works from the
 * token and reports it. Suppression hides the same errors wherever it is
 * written, so the extra report is kept deliberately — this test exists to stop
 * someone "fixing" it back to parity.
 */
it('flags error-control operators at file scope, where PHPMD stays silent', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'divergences.php');

    expect(violationTuples($file))->toBe(SILENCED_ERRORS_DIVERGENCES);
});
