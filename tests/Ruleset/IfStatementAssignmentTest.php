<?php

/**
 * Integration test for the Generic.CodeAnalysis.AssignmentInCondition rule as
 * configured in the master rules.xml, which replaces PHPMD's
 * CleanCode/IfStatementAssignment rule (issue #79). Fixtures live in
 * tests/fixtures/AssignmentInConditionSniff/.
 *
 * The fixtures partition the two tools' behaviour, each partition verified
 * against phpmd 2.15 running rulesets/cleancode.xml/IfStatementAssignment:
 *
 * - passing.php — neither tool reports anything.
 * - failing.php — both tools report the same lines, the same number of times
 *   each. This is the "no gaps" half of the mapping. It also carries the
 *   short-list destructuring form, which the custom sniff deliberately leaves
 *   to the Generic sniff (see IF_STATEMENT_ASSIGNMENT_SHARED).
 * - divergences.php — only the Generic sniff reports. PHPMD's rule reads
 *   if/elseif clauses only, accepts a plain "=" only, and visits function and
 *   method bodies only, so compound operators, the other conditions, and
 *   file-scope code fall outside it. Kept rather than narrowed: it is the same
 *   smell, no other PHPMD rule owns those shapes, and the sniff's two codes
 *   group the constructs together so the subset is not expressible as
 *   configuration.
 * - list-gap.php — only PHPMD reports, because the Generic sniff's
 *   left-hand-side walk abandons a list() destructuring target at its closing
 *   parenthesis. This is the gap the custom
 *   CleanCode.Conditionals.DisallowListAssignmentInCondition sniff closes, so
 *   the assertion here is that the *master ruleset* reports those lines even
 *   though the Generic sniff alone does not.
 *
 * Every fixture is run through both sniffs at once, and only those two, so a
 * sibling standard landing in rules.xml cannot shift the line map. Both are
 * needed in every run: the point of the mapping is that together they cover
 * what PHPMD covers, and asserting the source on each report is what keeps the
 * division of labour between them visible.
 *
 * Two properties beyond plain detection are pinned. The sniff reports warnings
 * out of the box; rules.xml raises its Found code to an error so an assignment
 * in a condition fails a phpcs run the way it fails a phpmd run. It also has no
 * fixer, matching PHPMD, which the fixable-count assertion pins.
 *
 * Severity is split across the sniff's two codes (#157). Found stays the error
 * #79 wired. FoundInWhileCondition — the only code a while or do...while
 * condition reports under — keeps the sniff's own warning, because draining a
 * cursor with `while ($row = fetch())` is idiomatic rather than accidental.
 * Parity with PHPMD is unaffected: its rule reads if/elseif clauses only, so
 * every lowered report was already outside the mapping. The split shows up here
 * on divergences.php alone, which carries the suite's only two while cases;
 * failing.php has none, so the error-severity assertion below runs against it
 * unchanged.
 */

declare(strict_types=1);

const IF_STATEMENT_ASSIGNMENT_SNIFF = 'Generic.CodeAnalysis.AssignmentInCondition';

const IF_STATEMENT_ASSIGNMENT_LIST_SNIFF = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

const IF_STATEMENT_ASSIGNMENT_FOUND = IF_STATEMENT_ASSIGNMENT_SNIFF . '.Found';

const IF_STATEMENT_ASSIGNMENT_IN_WHILE = IF_STATEMENT_ASSIGNMENT_SNIFF . '.FoundInWhileCondition';

/**
 * Every report on failing.php, at the line and column of the assignment
 * operator — the shapes PHPMD reports too, confirmed identical under phpmd
 * 2.15. Lines 20 and 28 carry two assignments each, so the pair also proves
 * the sniff reports per assignment rather than per line.
 *
 * Line 56 is short-list destructuring, and it carries the division of labour
 * between the two sniffs: the custom sniff excludes that form because the
 * Generic sniff's left-hand-side walk already accepts a target ending in "]".
 * Both sides are pinned by the one assertion — the entry goes red if the
 * Generic sniff ever stops reporting it, and its source goes red if the custom
 * sniff starts covering it as well.
 */
const IF_STATEMENT_ASSIGNMENT_SHARED = [
    ['line' => 14, 'column' => 18, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 16, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 20, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 20, 'column' => 35, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 24, 'column' => 28, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 37, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 32, 'column' => 29, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 36, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 40, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 20, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 45, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 56, 'column' => 41, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
];

/**
 * The error-severity reports on divergences.php — the shapes only the Generic
 * sniff reports, confirmed silent under phpmd 2.15, minus the two while cases
 * that #157 moved to a warning and that are pinned separately below.
 *
 * Every condition kind except while and do...while comes under the Found code
 * and stays an error, which is what proves the #157 downgrade is scoped to the
 * one code rather than to the sniff: lines 36 (for), 44 (switch), 45 (case) and
 * 49 (match) all sit here.
 */
const IF_STATEMENT_ASSIGNMENT_BROADER = [
    ['line' => 20, 'column' => 21, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 24, 'column' => 23, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 28, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 36, 'column' => 36, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 44, 'column' => 26, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 45, 'column' => 25, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 49, 'column' => 32, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
    ['line' => 57, 'column' => 16, 'source' => IF_STATEMENT_ASSIGNMENT_FOUND],
];

/**
 * The warning-severity reports on divergences.php: every while and do...while
 * condition in the file, and nothing else. Line 32 is a while header, line 42
 * the trailing while of a do...while — the two shapes FoundInWhileCondition
 * covers, and the whole of what #157 lowered.
 */
const IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS = [
    ['line' => 32, 'column' => 24, 'source' => IF_STATEMENT_ASSIGNMENT_IN_WHILE],
    ['line' => 42, 'column' => 26, 'source' => IF_STATEMENT_ASSIGNMENT_IN_WHILE],
];

/**
 * Every report on list-gap.php — the long-form list() shapes PHPMD reports and
 * the Generic sniff does not. Each source names the custom sniff, which is
 * what makes this an assertion about the gap rather than about the total.
 */
const IF_STATEMENT_ASSIGNMENT_LIST_GAP = [
    ['line' => 18, 'column' => 35, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
    ['line' => 20, 'column' => 55, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
    ['line' => 24, 'column' => 33, 'source' => IF_STATEMENT_ASSIGNMENT_LIST_SNIFF . '.Found'],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(IF_STATEMENT_ASSIGNMENT_SNIFF);
});

/**
 * passing.php is deliberately discriminating: alongside the comparisons in
 * every condition-bearing construct, it carries the near misses both sniffs
 * must stay silent on — an assignment as an ordinary statement, a compound
 * assignment in a for loop's increment, and an array key written with "=>"
 * inside a condition, which is not an assignment at all.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'passing.php')
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every assignment PHPMD reports, at the same line', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_SHARED);
});

it('flags the conditions PHPMD overlooks too', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_BROADER);
});

/**
 * The #157 severity split, asserted from both sides at once.
 *
 * The error list is what stops the warning list passing vacuously: a ruleset
 * that lowered the whole sniff instead of the one code would empty the errors
 * and still satisfy an assertion that only read the warnings, and one that
 * dropped the override entirely would empty the warnings. Naming the source on
 * every entry is what separates the two codes, since both carry the same
 * message text and the same fixture reports under both.
 */
it('lowers the while-condition code to a warning and leaves every other condition an error', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(warningTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS)
        ->and(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_BROADER)
        ->and($file->getWarningCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS))
        ->and($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_BROADER));
});

/**
 * The bare <rule ref> that has to sit beside the code-scoped one in rules.xml.
 *
 * Without it, Ruleset::processRule reads a lone code-scoped ref as "include
 * only this message", sets the sniff's own severity to 0 and the named code's
 * to 5 — which silences FoundInWhileCondition altogether instead of lowering
 * it. That failure is invisible to an error-only assertion, because the errors
 * are identical either way; only a report that the while lines still exist
 * catches it. Asserted as a non-empty warning set on the fixture that carries
 * the only two while cases in the suite.
 */
it('keeps the while-condition code reporting rather than excluding it', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'divergences.php')
    );

    expect(array_column(warningTuples($file), 'source'))
        ->toBe(array_fill(0, count(IF_STATEMENT_ASSIGNMENT_WHILE_WARNINGS), IF_STATEMENT_ASSIGNMENT_IN_WHILE));
});

/**
 * The Generic sniff alone is silent on a long-form list() destructuring
 * target, so this asserts the gap is real — nothing sourced to that sniff —
 * *and* closed, every line PHPMD reports carried by the custom one. Asserting
 * only the lines would pass if the Generic sniff had quietly started covering
 * them.
 */
it('closes the list() destructuring gap with the custom sniff', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'list-gap.php')
    );

    expect(violationTuples($file))->toBe(IF_STATEMENT_ASSIGNMENT_LIST_GAP);
});

/**
 * Error, not warning. The sniff ships as a warning and rules.xml raises it, so
 * a dropped <type> element leaves detection identical and only a severity
 * assertion catches it — an error is what makes phpcs fail the run the way
 * phpmd does.
 */
it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_SHARED))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The fixable-count assertion comes with the error count beside it: an empty
 * report also has zero fixable violations, so the count is what stops this
 * passing vacuously.
 */
it('reports without offering an auto-fix', function (): void {
    $file = analyzeWithSniffs(
        [IF_STATEMENT_ASSIGNMENT_SNIFF, IF_STATEMENT_ASSIGNMENT_LIST_SNIFF],
        fixturePath('AssignmentInConditionSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(IF_STATEMENT_ASSIGNMENT_SHARED))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
