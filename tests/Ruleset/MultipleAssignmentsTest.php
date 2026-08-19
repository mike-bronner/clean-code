<?php

/**
 * Integration test for the Squiz.PHP.DisallowMultipleAssignments rule as
 * configured in the master rules.xml, which carries the chained-assignment half
 * of Clear Code: One Idea Per Statement (issue #157, spun out of #8). Fixtures
 * live in tests/fixtures/DisallowMultipleAssignmentsSniff/.
 *
 * The standard's semantic core — whether a statement expresses a single idea —
 * is a judgement call and stays with code review. Two shapes are not: a chained
 * assignment binds several names at once, and an assignment written inside a
 * condition buries one idea in another. This sniff covers the first; the second
 * is Generic.CodeAnalysis.AssignmentInCondition, wired beside it and tested in
 * IfStatementAssignmentTest.php.
 *
 * The fixtures partition the sniff's real behaviour, every line of it measured
 * against the vendored PHP_CodeSniffer rather than taken from the issue:
 *
 * - passing.php — silent. One assignment per statement, plus every near miss
 *   that separates a chain from a construct that only looks like one.
 * - failing.php — the chained-assignment shape itself, all under the Found
 *   code.
 * - divergences.php — where the sniff and the heuristic disagree, in both
 *   directions: condition assignments it reports although the chain heuristic
 *   does not ask for them, and chains it stays silent on.
 *
 * Three silences are the sniff's own limits rather than choices made here, and
 * each is pinned so it cannot change unnoticed on a PHPCS upgrade:
 *
 * - It registers T_EQUAL only, so a chain built with a compound operator
 *   (`$total = $accumulated .= 'x';`) is invisible to it.
 * - It returns on any assignment nested in a while condition, so
 *   `while ($a = $b = $c)` is silent here — reported instead, at warning
 *   severity, by the sibling sniff's FoundInWhileCondition code.
 * - It exempts parameter defaults and property defaults, which are
 *   declarations rather than statements.
 */

declare(strict_types=1);

const MULTIPLE_ASSIGNMENTS_SNIFF = 'Squiz.PHP.DisallowMultipleAssignments';

const MULTIPLE_ASSIGNMENTS_FOUND = MULTIPLE_ASSIGNMENTS_SNIFF . '.Found';

const MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE = MULTIPLE_ASSIGNMENTS_SNIFF . '.FoundInControlStructure';

const ASSIGNMENT_IN_CONDITION_SNIFF = 'Generic.CodeAnalysis.AssignmentInCondition';

/**
 * Every PHP file the package ships or tests with, fixtures excluded — the same
 * tree `composer lint` reads, and the subject of the self-lint assertion below.
 *
 * Fixtures are excluded because they exist to be violated: several of them hold
 * chained assignments and condition assignments on purpose.
 *
 * A closure rather than a function so this file declares no global name, and
 * built the same way tests/Standards/ConvertToCollectionTest.php builds its own
 * sweep.
 *
 * @var callable(): array<int, string>
 */
$multipleAssignmentsPackageFiles = static function (): array {
    $root = cleanCodeRoot();
    $paths = [];

    foreach (['CleanCode', 'tests'] as $tree) {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($entries as $entry) {
            $path = $entry->getPathname();

            if (str_ends_with($path, '.php') === true && str_contains($path, '/fixtures/') === false) {
                $paths[] = $path;
            }
        }
    }

    sort($paths);

    return $paths;
};

/**
 * Every report on failing.php, at the line and column of the assignment
 * operator that binds the extra name.
 *
 * Line 20 carries a three-target chain and reports twice, which is what proves
 * the sniff reports once per binding beyond the first rather than once per
 * line. A per-line sniff would report it once and still satisfy a line-only
 * assertion.
 */
const MULTIPLE_ASSIGNMENTS_CHAINED = [
    ['line' => 18, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 20, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 20, 'column' => 35, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 22, 'column' => 35, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 24, 'column' => 40, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 35, 'column' => 29, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
];

/**
 * Every report on divergences.php from this sniff alone.
 *
 * The code on each entry is the assertion's whole point. Lines 25, 30 and 39
 * come under FoundInControlStructure because the assignment sits inside an
 * if/switch/match header's own parentheses; line 34 is a case expression, which
 * carries no parentheses of its own, so the sniff finds no owning control
 * structure and falls back to Found. A source-blind assertion would let the two
 * codes swap places unnoticed.
 *
 * The lines absent here matter as much as the ones present: 47, 54, 58 and 61
 * all carry assignments this sniff deliberately or structurally does not
 * report.
 */
const MULTIPLE_ASSIGNMENTS_BOUNDARIES = [
    ['line' => 25, 'column' => 21, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
    ['line' => 30, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
    ['line' => 34, 'column' => 25, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
    ['line' => 39, 'column' => 32, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTIPLE_ASSIGNMENTS_SNIFF);
});

/**
 * passing.php is deliberately discriminating. Alongside ordinary one-per-
 * statement assignments it carries every shape the sniff returns early on — a
 * property default, parameter defaults on a method, a closure and an arrow
 * function, a for-loop initialiser, a while condition, a compound-operator
 * chain, and a braceless control-structure body. Each exemption is reached by a
 * different early return, so removing any one of them reddens this file.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'passing.php')
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every binding beyond the first in a chained assignment', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
    );

    expect(violationTuples($file))->toBe(MULTIPLE_ASSIGNMENTS_CHAINED);
});

/**
 * Error, not warning. The sniff reports errors out of the box and rules.xml
 * adds no <type>, so this pins the absence of an override as much as the
 * severity: a <type>warning</type> added to that rule element would leave
 * detection identical and only this assertion would catch it.
 *
 * The error count sits beside the zero-warning assertion because an empty
 * report also has zero warnings, and would otherwise pass here vacuously.
 */
it('reports at error severity rather than as a warning', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(MULTIPLE_ASSIGNMENTS_CHAINED))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * No fixer. Splitting `$a = $b = $c;` has to choose the order the names are
 * bound in, which is a guess at intent rather than a mechanical rewrite. The
 * error count is what stops the fixable count passing vacuously.
 */
it('reports without offering an auto-fix', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'failing.php')
    );

    expect($file->getErrorCount())->toBe(count(MULTIPLE_ASSIGNMENTS_CHAINED))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('separates the control-structure code from the plain one', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe(MULTIPLE_ASSIGNMENTS_BOUNDARIES)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The known boundary between the two rules, asserted rather than left to be
 * rediscovered: a chain written inside a while header.
 *
 * This sniff returns before it can see it, so the shape the standard most wants
 * flagged — several names bound at once — is reported only by the sibling
 * sniff, and only as a warning. Running both sniffs over the fixture and
 * asserting the full tuple set is what makes this an assertion about the
 * division of labour rather than about either sniff alone: line 47's two
 * warnings are sourced entirely to FoundInWhileCondition, with nothing from
 * this sniff beside them.
 */
it('leaves a chain inside a while header to the sibling sniff, at warning severity', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
    );

    expect(warningTuples($file))->toBe([
        ['line' => 47, 'column' => 25, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
        ['line' => 47, 'column' => 35, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
        ['line' => 54, 'column' => 26, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.FoundInWhileCondition'],
    ]);
});

/**
 * The package's own source, read through the two rules this standard wires.
 *
 * A rule the standard's own code breaks is a rule that will be turned off by
 * whoever adopts it, so wiring one means bringing this tree into line with it —
 * which this change did, by splitting four memoise-and-return statements in
 * CleanCode/ and rewriting twenty configure callbacks in tests/ from an
 * assignment-bodied arrow function into a closure.
 *
 * Scoped to these two sniffs on purpose, and the scope is the honest claim
 * rather than a convenience. The whole ruleset run over this tree reports
 * thousands of pre-existing violations from unrelated rules — it did before this
 * change and it does after — so an "exits 0" assertion over the full standard
 * would be asserting someone else's backlog and would have to be either
 * suppressed or abandoned. tests/Standards/ManualModelResolutionTest.php sets
 * the precedent this follows: pin an exact, named violation set for the rule
 * under test, and record any survivor rather than silence it.
 *
 * Errors are the bar. The 27 warnings are the while-condition code, which this
 * change lowered precisely because the idiom is legitimate and widespread — this
 * tree's own use of it is the evidence, and counting them here is what would
 * catch the lowering being reverted.
 */
it('reports no errors from either rule against the package source', function () use (
    $multipleAssignmentsPackageFiles
): void {
    $sources = [];
    $warnings = 0;

    foreach ($multipleAssignmentsPackageFiles() as $path) {
        $file = analyzeWithSniffs([MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF], $path);

        $sources = array_merge($sources, array_column(violationTuples($file), 'source'));
        $warnings += $file->getWarningCount();
    }

    expect($sources)->toBe([])
        ->and($warnings)->toBe(27);
});

/**
 * Where the two rules agree, each still reports in its own words.
 *
 * An assignment inside an if/switch/case/match header breaks both "one idea per
 * statement" readings at once, and both sniffs say so. Pinning the doubled
 * lines makes the overlap a recorded decision rather than a surprise, and would
 * redden if either rule were narrowed to remove it.
 */
it('reports a condition assignment under both rules', function (): void {
    $file = analyzeWithSniffs(
        [MULTIPLE_ASSIGNMENTS_SNIFF, ASSIGNMENT_IN_CONDITION_SNIFF],
        fixturePath('DisallowMultipleAssignmentsSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe([
        ['line' => 25, 'column' => 21, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 25, 'column' => 21, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 30, 'column' => 26, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 30, 'column' => 26, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 34, 'column' => 25, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 34, 'column' => 25, 'source' => MULTIPLE_ASSIGNMENTS_FOUND],
        ['line' => 39, 'column' => 32, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
        ['line' => 39, 'column' => 32, 'source' => MULTIPLE_ASSIGNMENTS_IN_CONTROL_STRUCTURE],
        ['line' => 61, 'column' => 34, 'source' => ASSIGNMENT_IN_CONDITION_SNIFF . '.Found'],
    ]);
});
