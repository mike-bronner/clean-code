<?php

/**
 * Tests the custom CleanCode.Conditionals.DisallowListAssignmentInCondition
 * sniff, which closes the one detection gap between PHPMD's
 * IfStatementAssignment rule (#79) and Generic.CodeAnalysis.AssignmentInCondition
 * — a list() destructuring target in a condition.
 *
 * Fixtures live in tests/fixtures/DisallowListAssignmentInConditionSniff/.
 * There is no autofixed.php: the sniff is report-only, matching PHPMD, because
 * turning a destructuring assignment into a comparison is a guess at intent
 * rather than a mechanical rewrite. "reports without offering an auto-fix"
 * pins that.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * narrowed to it) so these assertions stay stable as sibling standards land in
 * rules.xml.
 */

declare(strict_types=1);

const DISALLOW_LIST_ASSIGNMENT = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

const DISALLOW_LIST_ASSIGNMENT_FOUND = DISALLOW_LIST_ASSIGNMENT . '.Found';

/**
 * Every condition-bearing construct the sniff covers, at the line and column
 * of the "=" it reports, in fixture order: if, elseif, keyed list() in an if,
 * a list() nested in a call argument in an if, while, the condition section of
 * a for, that same section reached through a closure, then that same section
 * beside each remaining construct PHPCS scopes in a for header — an arrow
 * function in the initialiser, an arrow function in the increment, an
 * anonymous class, a match — and finally do-while, switch, match, and an if at
 * file scope.
 *
 * passing.php holds each of those same for-header shapes with the list() in a
 * section that is *not* the condition, so the two fixtures bracket the
 * separator scan from both sides.
 *
 * Which line pins what, measured by running each mutation rather than assumed.
 * Two of the four are honestly not reddenable, and are kept as construct
 * coverage only:
 *
 * - 44 (closure) and 79 (anonymous class) — both die if the scan stops
 *   skipping braced bodies. 79 additionally dies on its own if the skip is
 *   written per-construct and omits anonymous classes, which 44 does not
 *   catch.
 * - 55 (arrow function in the initialiser) — the regression the third review
 *   round fixed, and the only line that dies if the scan jumps every
 *   scope_closer rather than only a brace.
 * - 64 (arrow function in the increment) — no mutation of the scan reddens
 *   this, and none can: the header's two separators are both collected before
 *   the scan reaches the increment, so whatever the scan does there cannot
 *   change the answer. Kept because the increment is where an arrow function's
 *   scope_closer is the header's closing parenthesis rather than a semicolon,
 *   so the shape is worth holding against a future scan that gathers
 *   separators differently.
 * - 88 (match) — likewise not reddenable: match arms are expressions and hold
 *   no semicolons, so skipping the match or not cannot change the separator
 *   list. Kept to record that the construct was considered.
 */
const DISALLOW_LIST_ASSIGNMENT_VIOLATIONS = [
    ['line' => 14, 'column' => 35, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 16, 'column' => 55, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 33, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 26, 'column' => 42, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 30, 'column' => 38, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 34, 'column' => 51, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 44, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 55, 'column' => 78, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 64, 'column' => 59, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 79, 'column' => 46, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 88, 'column' => 47, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 94, 'column' => 46, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 96, 'column' => 47, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 101, 'column' => 54, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
    ['line' => 107, 'column' => 45, 'source' => DISALLOW_LIST_ASSIGNMENT_FOUND],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_LIST_ASSIGNMENT);
});

/**
 * passing.php is deliberately discriminating rather than merely silent: beside
 * the ordinary comparisons it carries a list() assignment in a for loop's
 * initialiser and increment sections — including the closure, arrow-function,
 * anonymous-class and match shapes failing.php places in the condition
 * section — which is where an assignment is ordinary and must stay unreported.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Exact line, column, and source for every report. The list is the sniff's
 * whole detection contract, so a missed construct and an extra report both
 * fail here.
 */
it('flags a list() assignment in every condition-bearing construct', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect(violationTuples($file))->toBe(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS);
});

/**
 * Error, not warning. PHPMD fails a run on this violation, and a warning would
 * leave phpcs exiting 0 — so phpmd would still have to run separately for it.
 */
it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect($file->getErrorCount())->toBe(count(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The fixable-count assertion comes with the error count beside it: an empty
 * report also has zero fixable violations, so the count is what stops this
 * passing vacuously.
 */
it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'failing.php');

    expect($file->getErrorCount())->toBe(count(DISALLOW_LIST_ASSIGNMENT_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * A for header with fewer than two section separators has no condition section
 * to sit in. Only malformed source can produce one, and the sniff answers "not
 * a condition" rather than guessing at a span.
 *
 * The fixture reaches the guard three ways — no separator at all, and one
 * separator with the list() on either side of it — so the guard is exercised,
 * not just asserted around.
 *
 * The assertions pin the outcome: nothing reported, nothing crashed. The guard
 * itself is load-bearing on top of that, and this is the test that proves it:
 * removing it turns this case red, because the error handler surfaces the
 * undefined-key read of the second separator that follows. Both halves were
 * checked by running that mutation.
 */
it('treats a for header without two section separators as no condition section', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'malformed-for-header.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * An unterminated list() carries a null closer, so the sniff has no position to
 * look past for an "=".
 *
 * This pins the outcome, not the guard: removing the null-closer check leaves
 * this fixture silent too, because the search from the resulting bogus offset
 * lands on a token that is not "=". No fixture can separate the two, so the
 * guard is documented in the sniff as explicitness rather than claimed as
 * tested behaviour. What is proven here is that malformed source produces no
 * report and no crash.
 */
it('refuses an unterminated list() rather than guessing at it', function (): void {
    $file = analyzeFixture(DISALLOW_LIST_ASSIGNMENT, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
