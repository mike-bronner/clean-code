<?php

/**
 * Integration test for the VariableAnalysis.CodeAnalysis.VariableAnalysis
 * rule's UnusedVariable code as configured in the master rules.xml, which
 * replaces PHPMD's UnusedCode UnusedLocalVariable rule (issue #118). Fixtures
 * live in tests/fixtures/VariableAnalysisSniff/.
 *
 * One sniff carries two PHPMD rules here. tests/Ruleset/UndefinedVariableTest
 * covers the same sniff's UndefinedVariable half (#85) and the three codes
 * rules.xml excludes; this file covers UnusedVariable and nothing else, so the
 * two rules can be read — and can fail — independently.
 *
 * Unlike #85, this rule *is* configured: rules.xml sets three properties to
 * make the sniff's UnusedVariable code answer the question PHPMD asks. Every
 * one of them is pinned below by a pair of tests — the configured behaviour,
 * and the same fixture under the opposite setting. Without the second half a
 * property could be deleted from rules.xml and every "stays silent" assertion
 * would still pass, because the shape would simply never be reached.
 *
 * The severity override is pinned too. The sniff reports warnings out of the
 * box and rules.xml raises them to errors, so an unused local fails a phpcs
 * run the way it fails a phpmd run — which is what lets phpmd stop running for
 * this rule at all. That is why these tests assert the reports land in
 * getErrors() and that getWarnings() stays empty.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const UNUSED_LOCAL_VARIABLE_SNIFF = 'VariableAnalysis.CodeAnalysis.VariableAnalysis';

const UNUSED_VARIABLE = UNUSED_LOCAL_VARIABLE_SNIFF . '.UnusedVariable';

/**
 * Every line the configured rule reports on unused-locals.php, with the column
 * each report lands on. PHPMD 2.15.0, run with only UnusedLocalVariable
 * enabled, reports these same ten lines on this same fixture and nothing else.
 *
 * Shared by the parity test and by the property tests below, which each assert
 * this list minus the one line their property is expected to silence. Written
 * once so a fixture edit cannot leave the two halves disagreeing.
 */
const UNUSED_LOCAL_PARITY_SET = [
    [23, 9],      // $i = 5;                       simple assignment
    [37, 27],     // foreach ($rows as $row)       foreach value
    [51, 27],     // foreach ($rows as $key => …)  foreach key
    [65, 35],     // foreach (… => $value)         foreach associative value
    [77, 17],     // [$left, $right] = $pair;      destructured element
    [88, 16],     // static $counter = 0;          static local
    [98, 16],     // global $registry;             imported global
    [110, 13],    // $innerUnused = 1;             closure local
    [122, 9],     // $alias = &$source;            reference alias
    [133, 9],     // $before = 'unused';           assigned before extract()
];

/**
 * The parity set as violationTuples() returns it, optionally dropping the
 * lines a property under test is expected to silence.
 *
 * A closure rather than a named function, matching UndefinedVariableTest: a
 * file that both declares symbols and runs `it()` calls trips PSR-12's
 * Files.SideEffects warning in `composer lint`.
 */
$parityTuples = static function (array $withoutLines = []): array {
    $tuples = [];

    foreach (UNUSED_LOCAL_PARITY_SET as [$line, $column]) {
        if (in_array($line, $withoutLines, true)) {
            continue;
        }

        $tuples[] = ['line' => $line, 'column' => $column, 'source' => UNUSED_VARIABLE];
    }

    return $tuples;
};

/**
 * Processes a fixture through the master ruleset with one sniff property
 * overridden, the way a consuming ruleset would override it. Used only by the
 * "without this property" half of each pair below.
 */
$analyzeOverridden = static function (string $fixture, string $property, $value): LocalFile {
    return analyzeFixture(
        UNUSED_LOCAL_VARIABLE_SNIFF,
        $fixture,
        static function (object $sniff) use ($property, $value): void {
            $sniff->{$property} = $value;
        }
    );
};

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The parity set, asserted line *and* column so a fixture edit that shifted a
 * report onto a different token could not pass by line number alone.
 */
it('flags each unused local at its own line and column', function () use ($parityTuples): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect(violationTuples($file))->toBe($parityTuples());
});

/**
 * rules.xml raises the sniff's built-in warning to an error. Without it phpcs
 * exits 0 on an unused local, and phpmd would still have to run for this rule
 * — the one thing issue #118 exists to stop.
 */
it('reports unused locals as errors rather than warnings', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * PHPMD reports this rule rather than rewriting the code, and deleting an
 * unused assignment can drop a side effect in the expression that produced it
 * — so no diagnostic may carry a fixer hook.
 *
 * The violation count is asserted first: an empty report also has zero fixable
 * violations, so without it silencing the sniff would make this pass.
 */
it('reports unused locals without offering an auto-fix', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(UNUSED_LOCAL_PARITY_SET), false));
});

/**
 * The "autofixed" half of the fixture contract, for a rule that has no
 * autofixed.php to compare against: the fixer's real output on
 * unused-locals.php *is* unused-locals.php, byte for byte.
 *
 * Proven by driving the same Fixer phpcbf drives, not by trusting the fixable
 * flag the test above reads. The error count is asserted both before and after
 * the run, so a fixture that stopped tripping the sniff — or a fixer that
 * silently swallowed every diagnostic — cannot pass this vacuously.
 */
it('leaves the unused-locals fixture byte-identical when the fixer runs', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals.php');

    expect($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET));

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('VariableAnalysisSniff', 'unused-locals.php')))
        ->and($file->getErrorCount())->toBe(count(UNUSED_LOCAL_PARITY_SET));
});

/**
 * allowUnusedFunctionParameters=true — the boundary with PHPMD's separate
 * UnusedFormalParameter rule (#120). PHPMD's UnusedLocalVariable drops formal
 * parameters in removeParameters(); the sniff has one code for locals and
 * parameters alike, so rules.xml silences the parameter half here.
 *
 * passing.php::unusedParameter() carries a parameter nobody reads.
 */
it('leaves an unused formal parameter to the UnusedFormalParameter rule', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same parameter without the configured property', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedFunctionParameters', false);

    expect(violationTuples($file))->toBe([
        ['line' => 81, 'column' => 44, 'source' => UNUSED_VARIABLE],    // string $ignored
    ]);
});

/**
 * allowUnusedVariablesInFileScope=true — PHPMD's rule is FunctionAware and
 * MethodAware only, so it never looks at a file's top-level scope. The sniff
 * does by default; rules.xml restores parity.
 *
 * passing.php ends with a top-level assignment nobody reads.
 */
it('ignores an unused assignment in the file scope', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same file-scope assignment without the property', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedVariablesInFileScope', false);

    expect(violationTuples($file))->toBe([
        ['line' => 168, 'column' => 1, 'source' => UNUSED_VARIABLE],    // $fileScopeAssignment
    ]);
});

/**
 * allowUnusedCaughtExceptions — left at the sniff's default of true, which
 * already matches PHPMD: isNameAllowedInContext() exempts any variable bound
 * by a catch statement. Pinned anyway, because "matches by default" is exactly
 * the kind of agreement a vendor upgrade can end silently.
 */
it('ignores a caught exception nobody reads', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

it('would flag that same caught exception without the sniff default', function () use ($analyzeOverridden): void {
    $file = $analyzeOverridden('passing.php', 'allowUnusedCaughtExceptions', false);

    expect(violationTuples($file))->toBe([
        ['line' => 94, 'column' => 28, 'source' => UNUSED_VARIABLE],    // catch (Throwable $error)
    ]);
});

/**
 * allowUnusedForeachVariables=false — matches PHPMD's own default for
 * allow-unused-foreach-variables, so an unused foreach key or value is
 * flagged. The sniff's default is the opposite, hence the explicit set; the
 * configured behaviour is already covered by the parity test above.
 *
 * Flipping it exposes a documented divergence rather than parity, so this half
 * asserts what the sniff actually does: PHPMD's property silences all three
 * foreach shapes, while the sniff's silences only the `key => value` value —
 * its guard reads isForeachLoopAssociativeValue. Lines 37 and 51 survive.
 */
it('silences only the associative foreach value when the property is flipped', function () use (
    $analyzeOverridden,
    $parityTuples
): void {
    $file = $analyzeOverridden('unused-locals.php', 'allowUnusedForeachVariables', true);

    expect(violationTuples($file))->toBe($parityTuples([65]));
});

/**
 * PHPMD's `exceptions` property takes a comma-separated list of names to skip.
 * The sniff's analogue is validUnusedVariableNames, which is space-separated;
 * rules.xml leaves it unset, matching PHPMD's own empty default.
 *
 * Asserting the whole remaining set, not just the absence of $i, proves the
 * property is selective — a value that silenced the sniff outright would fail
 * here.
 */
it('honours the exceptions analogue for one name without silencing the rest', function () use (
    $analyzeOverridden,
    $parityTuples
): void {
    $file = $analyzeOverridden('unused-locals.php', 'validUnusedVariableNames', 'i');

    expect(violationTuples($file))->toBe($parityTuples([23]));
});

/**
 * Pins the one shape where this ruleset is noisier than PHPMD, and — by
 * asserting the whole map — the one shape neither tool reports.
 *
 * PHPMD 2.15.0 reports line 31 alone on this fixture. This ruleset reports 31
 * and 32, one per assignment to the same unused name. Line 49, the
 * self-referential dead store, is absent from both: it would appear in this
 * map if the sniff caught it.
 */
it('reports every assignment to an unused name where PHPMD reports one', function (): void {
    $file = analyzeFixture(UNUSED_LOCAL_VARIABLE_SNIFF, 'unused-locals-divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        31 => [UNUSED_VARIABLE],    // $total = 1;  — PHPMD reports here too
        32 => [UNUSED_VARIABLE],    // $total = 2;  — PHPMD stays silent here
    ]);
});
