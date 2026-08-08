<?php

/**
 * Integration test for the Generic.CodeAnalysis.UnusedFunctionParameter rule as
 * configured in the master rules.xml, which replaces PHPMD's UnusedCode
 * UnusedFormalParameter rule (issue #120). Fixtures live in
 * tests/fixtures/UnusedFunctionParameterSniff/.
 *
 * The sniff emits nine codes. rules.xml keeps the three that fire where no
 * signature is imposed from outside, and excludes the six it swaps in when the
 * enclosing class extends a class or implements an interface — because PHPMD
 * exempts a parameter that only exists to satisfy an inherited signature. Both
 * halves are pinned below: the excluded codes stay silent through rules.xml,
 * and the same fixture proves they would fire without the excludes, so dropping
 * an <exclude> fails this suite.
 *
 * Beyond the contract's passing.php and failing.php, two extra fixtures carry
 * shapes belonging to neither set:
 *
 * - divergences.php — every shape where this ruleset and PHPMD disagree, in
 *   both directions. Pinned by a test so the gap cannot drift back into an
 *   unearned parity claim; described in
 *   docs/phpmd/unusedcode-unusedformalparameter.md.
 * - excluded-codes.php — the six sniff codes rules.xml excludes.
 *
 * There is no autofixed.php: the rule is not fixable, and the byte-identical
 * test below proves it by running the real fixer rather than asserting the
 * absence.
 *
 * The severity override is pinned here too. The sniff reports warnings out of
 * the box and rules.xml raises them to errors, so an unused parameter fails a
 * phpcs run the way it fails a phpmd run — which is what lets phpmd stop
 * running for this rule at all. That is why these tests assert the reports land
 * in getErrors() and that getWarnings() stays empty.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const UNUSED_PARAMETER_SNIFF = 'Generic.CodeAnalysis.UnusedFunctionParameter';

const UNUSED_PARAMETER_FOUND = UNUSED_PARAMETER_SNIFF . '.Found';

const UNUSED_PARAMETER_BEFORE_LAST_USED = UNUSED_PARAMETER_SNIFF . '.FoundBeforeLastUsed';

const UNUSED_PARAMETER_AFTER_LAST_USED = UNUSED_PARAMETER_SNIFF . '.FoundAfterLastUsed';

const UNUSED_PARAMETER_EXCLUDED_CODES = [
    UNUSED_PARAMETER_SNIFF . '.FoundInExtendedClass',
    UNUSED_PARAMETER_SNIFF . '.FoundInExtendedClassBeforeLastUsed',
    UNUSED_PARAMETER_SNIFF . '.FoundInExtendedClassAfterLastUsed',
    UNUSED_PARAMETER_SNIFF . '.FoundInImplementedInterface',
    UNUSED_PARAMETER_SNIFF . '.FoundInImplementedInterfaceBeforeLastUsed',
    UNUSED_PARAMETER_SNIFF . '.FoundInImplementedInterfaceAfterLastUsed',
];

/**
 * Processes a fixture through the *unconfigured* sniff — as PHP_CodeSniffer
 * ships it inside the bundled Generic standard, with none of rules.xml's
 * excludes and none of its severity override. Under it the sniff reports
 * warnings, not errors, which is why every caller reads through
 * allViolationSourcesByLine().
 *
 * The shared buildRuleset() helper always builds rules.xml, which is precisely
 * what the "without our config" tests below have to exclude. Restricting
 * Config::$sniffs is safe here for the same reason it is banned in Helpers.php:
 * under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip the rules.xml parse
 * outright, and skipping it is the point. Nothing is memoised — each call
 * resets PHPCS's static config state through a fresh ConfigDouble, so it cannot
 * leak into a helper-built ruleset later in the run.
 */
$analyzeUnconfigured = static function (string $fixture): LocalFile {
    $config = new ConfigDouble();
    $config->cache = false;
    $config->standards = ['Generic'];
    $config->sniffs = [UNUSED_PARAMETER_SNIFF];

    $file = new LocalFile(
        fixturePath('UnusedFunctionParameterSniff', $fixture),
        new Ruleset($config),
        $config
    );
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_PARAMETER_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The parity set: PHPMD 2.15.0, run with only UnusedFormalParameter enabled,
 * reports these same six lines on this same fixture and nothing else.
 *
 * Columns are asserted alongside the lines. The sniff attaches every report to
 * the declaration token rather than to the parameter, so a plain function lands
 * on column 1 and a method on the column of its visibility keyword — which is
 * what makes the docblock-only case (line 41) and the magic __invoke case
 * (line 46) distinguishable from a report attached to the parameter itself.
 */
it('flags each unused parameter at its own line and column', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe([
        // function onlyParameterUnused(string $unused)
        ['line' => 14, 'column' => 1, 'source' => UNUSED_PARAMETER_FOUND],
        // function unusedBeforeLastUsed(string $unusedFirst, string $used)
        ['line' => 19, 'column' => 1, 'source' => UNUSED_PARAMETER_BEFORE_LAST_USED],
        // function unusedAfterLastUsed(string $used, string $unusedSecond)
        ['line' => 24, 'column' => 1, 'source' => UNUSED_PARAMETER_AFTER_LAST_USED],
        // StandaloneReporter::record(string $unusedReason)
        ['line' => 31, 'column' => 12, 'source' => UNUSED_PARAMETER_FOUND],
        // StandaloneReporter::mentionInDocblockOnly(string $ghost)
        ['line' => 41, 'column' => 12, 'source' => UNUSED_PARAMETER_FOUND],
        // StandaloneReporter::__invoke(string $unusedInvoked)
        ['line' => 46, 'column' => 12, 'source' => UNUSED_PARAMETER_FOUND],
    ]);
});

/**
 * rules.xml raises the sniff's built-in warning to an error. Without it phpcs
 * exits 0 on an unused parameter, and phpmd would still have to run for this
 * rule — the one thing issue #120 exists to stop.
 */
it('reports unused parameters as errors rather than warnings', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(6)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the three shapes where this ruleset is stricter than PHPMD, and — by
 * asserting the whole map — the four where it is looser.
 *
 * PHPMD 2.15.0 reports exactly four lines on this fixture (60, 75, 93, 104) and
 * this ruleset reports exactly three (36, 41, 50). The two sets do not
 * intersect, which is the point of the fixture: every line in it is a
 * disagreement. The two shapes both tools agree on — a genuine override at
 * line 66 and an interface implementation at line 81 — are absent from this map
 * and from PHPMD's, and would appear here if an <exclude> were dropped.
 *
 * Neither direction is configurable: the sniff exposes only ignoreTypeHints,
 * which exempts by type hint and cannot express any of these shapes. So the
 * gap is documented rather than tuned away, per the issue's own fallback
 * clause.
 */
it('flags the divergences from PHPMD exactly where they are recorded', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        36 => [UNUSED_PARAMETER_FOUND],    // closure parameter
        41 => [UNUSED_PARAMETER_FOUND],    // arrow-function parameter
        50 => [UNUSED_PARAMETER_FOUND],    // parameter reached via func_get_args()
    ]);
});

/**
 * Splits the silence in the test above by cause, because the four "looser than
 * PHPMD" lines are silent for two different reasons and only one of them is
 * ours.
 *
 * Lines 60 and 75 are silenced by rules.xml's <exclude>s — the unconfigured
 * sniff raises an InExtendedClass / InImplementedInterface code on each. Lines
 * 93 (empty body) and 104 (__unserialize) are silent on the sniff's own
 * account, so no exclude may be credited for them.
 *
 * The three stricter lines are asserted present in the same pass, so a fixture
 * that stopped parsing could not fake this.
 */
it('splits the looser divergences into excluded and never-reported', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('divergences.php');

    $sources = allViolationSourcesByLine($file);
    $flaggedLines = array_keys($sources);

    expect($flaggedLines)->toContain(36)
        ->and($flaggedLines)->toContain(41)
        ->and($flaggedLines)->toContain(50)
        ->and($sources[60])->toBe([UNUSED_PARAMETER_SNIFF . '.FoundInExtendedClass'])
        ->and($sources[75])->toBe([UNUSED_PARAMETER_SNIFF . '.FoundInImplementedInterface'])
        ->and($flaggedLines)->not->toContain(93)
        ->and($flaggedLines)->not->toContain(104);
});

/**
 * PHPMD reports this rule rather than rewriting the code, and deleting a
 * parameter changes the signature and breaks every caller — so no diagnostic
 * may carry a fixer hook.
 *
 * The violation count is asserted first: an empty report also has zero fixable
 * violations, so without it silencing the sniff would make this pass.
 */
it('reports unused parameters without offering an auto-fix', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false, false, false, false]);
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
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(6);

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('UnusedFunctionParameterSniff', 'failing.php')))
        ->and($file->getErrorCount())->toBe(6);
});

it('keeps the excluded codes silent through the master ruleset', function (): void {
    $file = analyzeFixture(UNUSED_PARAMETER_SNIFF, 'excluded-codes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Guards the test above from passing vacuously: the same fixture, run through
 * the unconfigured sniff, must raise every code rules.xml excludes. Without
 * this, a fixture that trips nothing at all would look like a working exclude
 * list.
 */
it('raises every excluded code without the master rulesets excludes', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('excluded-codes.php');

    $raised = array_merge(...array_values(allViolationSourcesByLine($file)));

    foreach (UNUSED_PARAMETER_EXCLUDED_CODES as $code) {
        expect($raised)->toContain($code);
    }
});
