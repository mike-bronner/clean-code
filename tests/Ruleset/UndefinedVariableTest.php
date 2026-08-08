<?php

/**
 * Integration test for the VariableAnalysis.CodeAnalysis.VariableAnalysis rule
 * as configured in the master rules.xml, which replaces PHPMD's CleanCode
 * UndefinedVariable rule (issue #85). Fixtures live in
 * tests/fixtures/VariableAnalysisSniff/.
 *
 * The sniff emits six codes; rules.xml keeps the two that mean "a variable is
 * read before it is defined" and excludes three that belong to no PHPMD rule.
 * Both halves are pinned below: the excluded codes stay silent through
 * rules.xml, and the same fixture proves they would fire without the excludes
 * — so dropping an <exclude> fails this suite.
 *
 * The sixth code, UnusedVariable, is neither kept for this rule nor excluded:
 * it carries PHPMD's UnusedLocalVariable (#118) and is covered by
 * tests/Ruleset/UnusedLocalVariableTest.php.
 *
 * Beyond the contract's passing.php and failing.php, two extra fixtures carry
 * shapes belonging to neither set:
 *
 * - divergences.php — where this ruleset and PHPMD differ, plus the shape
 *   neither tool catches. Pinned by a test so the gap cannot drift back into
 *   an unearned parity claim; described in
 *   docs/phpmd/cleancode-undefinedvariable.md.
 * - excluded-codes.php — the three sniff codes rules.xml excludes.
 *
 * There is no autofixed.php: the rule is not fixable, and the byte-identical
 * test below proves it by running the real fixer rather than asserting the
 * absence.
 *
 * The severity override is pinned here too. The sniff reports warnings out of
 * the box and rules.xml raises them to errors, so an undefined variable fails a
 * phpcs run the way it fails a phpmd run — which is what lets phpmd stop
 * running for this rule at all. That is why these tests assert the reports land
 * in getErrors() and that getWarnings() stays empty.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const VARIABLE_ANALYSIS_SNIFF = 'VariableAnalysis.CodeAnalysis.VariableAnalysis';

const UNDEFINED_VARIABLE = VARIABLE_ANALYSIS_SNIFF . '.UndefinedVariable';

const UNDEFINED_UNSET_VARIABLE = VARIABLE_ANALYSIS_SNIFF . '.UndefinedUnsetVariable';

const UNUSED_VARIABLE_CODE = VARIABLE_ANALYSIS_SNIFF . '.UnusedVariable';

const VARIABLE_ANALYSIS_EXCLUDED_CODES = [
    VARIABLE_ANALYSIS_SNIFF . '.VariableRedeclaration',
    VARIABLE_ANALYSIS_SNIFF . '.SelfOutsideClass',
    VARIABLE_ANALYSIS_SNIFF . '.StaticOutsideClass',
];

/**
 * Processes a fixture through the *unconfigured* VariableAnalysis standard —
 * the sniff as its vendor ships it, with none of rules.xml's excludes and none
 * of its severity override. Under it the sniff reports warnings, not errors,
 * which is why both callers read through allViolationSourcesByLine().
 *
 * The shared buildRuleset() helper always builds rules.xml, which is precisely
 * what the two "without our config" tests below have to exclude, so this builds
 * its own Config. Nothing is memoised: each call resets PHPCS's static config
 * state through a fresh ConfigDouble, so it cannot leak into a helper-built
 * ruleset later in the run.
 */
$analyzeUnconfigured = static function (string $fixture): LocalFile {
    $config = new ConfigDouble();
    $config->cache = false;
    $config->standards = ['VariableAnalysis'];

    Config::setConfigData(
        'installed_paths',
        cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
        true
    );

    $file = new LocalFile(
        fixturePath('VariableAnalysisSniff', $fixture),
        new Ruleset($config),
        $config
    );
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(VARIABLE_ANALYSIS_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The parity set: PHPMD 2.15.0, run with only UndefinedVariable enabled,
 * reports these same four lines on this same fixture and nothing else.
 */
it('flags each undefined read at its own line', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        17 => [UNDEFINED_VARIABLE],          // return $undefinedScalar;
        22 => [UNDEFINED_VARIABLE],          // return $undefinedArray['key'];
        27 => [UNDEFINED_VARIABLE],          // "value: {$undefinedInString}"
        32 => [UNDEFINED_UNSET_VARIABLE],    // unset($neverAssigned);
    ]);
});

/**
 * rules.xml raises the sniff's built-in warning to an error. Without it phpcs
 * exits 0 on an undefined variable, and phpmd would still have to run for this
 * rule — the one thing issue #85 exists to stop.
 */
it('reports undefined reads as errors rather than warnings', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the two shapes where this ruleset is stricter than PHPMD, and — by
 * asserting the whole map — the one shape neither tool reports.
 *
 * PHPMD 2.15.0 reports nothing at all on this fixture. This ruleset reports
 * exactly three lines. Line 75, the conditionally-assigned read, is absent
 * from both: it would appear in this map if the sniff caught it.
 *
 * Line 52 is the same closure-scope divergence as line 56, seen from the other
 * side. Because a closure body is its own scope to the sniff, $insideClosure is
 * both undefined at the read on line 56 and unused at the assignment on line
 * 52 — so enabling UnusedVariable for PHPMD's UnusedLocalVariable (#118) makes
 * this fixture report it twice. PHPMD folds the closure into its enclosing
 * method and so reports neither.
 *
 * Neither behaviour is configurable — VariableAnalysisSniff exposes no property
 * that toggles statement-order or closure scoping — so the gap is documented
 * rather than tuned away, per the issue's own fallback clause.
 */
it('flags the divergences from PHPMD exactly where they are recorded', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        34 => [UNDEFINED_VARIABLE],       // read one line above its assignment
        52 => [UNUSED_VARIABLE_CODE],     // closure local unused inside the closure
        56 => [UNDEFINED_VARIABLE],       // closure local read outside the closure
    ]);
});

/**
 * Guards the test above from crediting our own <exclude>s for the silence on
 * line 75: the same fixture, run through the unconfigured VariableAnalysis
 * standard, still says nothing about that line. The two divergence lines are
 * asserted present in the same pass, so a fixture that stopped parsing could
 * not fake this.
 */
it('stays blind to the conditional assignment on the sniffs own account', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('divergences.php');

    $flaggedLines = array_keys(allViolationSourcesByLine($file));

    expect($flaggedLines)->toContain(34)
        ->and($flaggedLines)->toContain(56)
        ->and($flaggedLines)->not->toContain(75);
});

/**
 * PHPMD reports this rule rather than rewriting the code, and an undefined
 * variable has no machine-derivable value to substitute — so no diagnostic may
 * carry a fixer hook.
 *
 * The violation count is asserted first: an empty report also has zero fixable
 * violations, so without it silencing the sniff would make this pass.
 */
it('reports undefined reads without offering an auto-fix', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false, false]);
});

/**
 * The "autofixed" half of the fixture contract, for a rule that has no
 * autofixed.php to compare against: the fixer's real output on failing.php *is*
 * failing.php, byte for byte.
 *
 * Proven by driving the same Fixer phpcbf drives, not by trusting the fixable
 * flag the test above reads. The error count is asserted both before and after
 * the run, so a fixture that stopped tripping the sniff — or a fixer that
 * silently swallowed every diagnostic — cannot pass this vacuously.
 */
it('leaves the failing fixture byte-identical when the fixer runs', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4);

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('VariableAnalysisSniff', 'failing.php')))
        ->and($file->getErrorCount())->toBe(4);
});

it('keeps the excluded codes silent through the master ruleset', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'excluded-codes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Guards the test above from passing vacuously: the same fixture, run through
 * the unconfigured VariableAnalysis standard, must raise every code rules.xml
 * excludes. Without this, a fixture that trips nothing at all would look like a
 * working exclude list.
 */
it('raises every excluded code without the master rulesets excludes', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('excluded-codes.php');

    $raised = array_merge(...array_values(allViolationSourcesByLine($file)));

    foreach (VARIABLE_ANALYSIS_EXCLUDED_CODES as $code) {
        expect($raised)->toContain($code);
    }
});
