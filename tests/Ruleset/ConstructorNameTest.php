<?php

/**
 * Integration test for the Generic.NamingConventions.ConstructorName rule as
 * configured in the master CleanCode/ruleset.xml, which replaces PHPMD's Naming
 * ConstructorWithNameAsEnclosingClass rule (issue #113). Fixtures live in
 * tests/fixtures/ConstructorNameSniff/.
 *
 * The sniff emits two codes; CleanCode/ruleset.xml keeps OldStyle (a PHP4-style constructor
 * declaration, which is PHPMD's rule) and excludes OldStyleCall (a PHP4-style
 * call to a parent constructor, which has no counterpart in it). Both halves are
 * pinned below: the excluded code stays silent through CleanCode/ruleset.xml, and the same
 * fixture proves it would fire without the exclude — so dropping the <exclude>
 * fails this suite.
 *
 * Beyond the contract's passing.php and failing.php, three extra fixtures carry
 * shapes belonging to neither set:
 *
 * - namespaced-divergence.php — the one shape where this ruleset is stricter
 *   than PHPMD.
 * - phpmd-only-divergences.php — the two shapes where PHPMD is stricter than
 *   this ruleset.
 * - excluded-codes.php — the sniff code CleanCode/ruleset.xml excludes.
 *
 * The two divergence files are separate on purpose. PHPMD's rule skips every
 * class outside the global namespace, and that guard is file-wide, so a
 * namespaced fixture silences PHPMD for a reason unrelated to the shape under
 * test — conflating the two would let the PHPMD-only divergences look like
 * agreement. Both are described in
 * docs/phpmd/naming-constructorwithnameasenclosingclass.md.
 *
 * There is no autofixed.php: the rule is not fixable, and the byte-identical
 * test below proves it by running the real fixer rather than asserting the
 * absence.
 *
 * Unlike the Squiz.PHP.Eval and VariableAnalysis mappings, no severity override
 * is needed — this sniff reports through addError() already. That is asserted
 * rather than assumed, so a vendor change to warning severity fails here instead
 * of quietly leaving phpcs exiting 0 on a PHP4 constructor.
 *
 * Every PHPMD claim in this file was verified by running PHPMD 2.15.0's
 * naming.xml ruleset against these exact fixtures, not read off its source.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const CONSTRUCTOR_NAME_SNIFF = 'Generic.NamingConventions.ConstructorName';

const CONSTRUCTOR_NAME_OLD_STYLE = CONSTRUCTOR_NAME_SNIFF . '.OldStyle';

const CONSTRUCTOR_NAME_OLD_STYLE_CALL = CONSTRUCTOR_NAME_SNIFF . '.OldStyleCall';

/**
 * Processes a fixture through the *unconfigured* Generic standard — the sniff
 * as PHPCS ships it, without CleanCode/ruleset.xml's exclude.
 *
 * The shared buildRuleset() helper always builds CleanCode/ruleset.xml, which is precisely
 * what the "without our config" tests below have to exclude, so this builds its
 * own Config. Generic is a bundled standard, so unlike the VariableAnalysis
 * equivalent it needs no installed_paths entry. Nothing is memoised: each call
 * resets PHPCS's static config state through a fresh ConfigDouble, so it cannot
 * leak into a helper-built ruleset later in the run.
 */
$analyzeUnconfigured = static function (string $fixture): LocalFile {
    $config = new ConfigDouble();
    $config->cache = false;
    $config->standards = ['Generic'];
    $config->sniffs = [CONSTRUCTOR_NAME_SNIFF];

    $file = new LocalFile(
        fixturePath('ConstructorNameSniff', $fixture),
        new Ruleset($config),
        $config
    );
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONSTRUCTOR_NAME_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The parity set: PHPMD 2.15.0, run with only ConstructorWithNameAsEnclosingClass
 * enabled, reports these same three lines on this same fixture and nothing else.
 *
 * Line 37 is the case-insensitivity boundary — MIXEDCASENAME against class
 * MixedCaseName. The sniff lowercases both names before comparing and PHPMD uses
 * strcasecmp(), so both flag it; asserting the whole map means a sniff that
 * became case-sensitive would drop line 37 and fail here.
 */
it('flags each PHP4 style constructor at its own line', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        18 => [CONSTRUCTOR_NAME_OLD_STYLE],    // class PlainOldStyle
        25 => [CONSTRUCTOR_NAME_OLD_STYLE],    // abstract class AbstractOldStyle
        37 => [CONSTRUCTOR_NAME_OLD_STYLE],    // MIXEDCASENAME vs MixedCaseName
    ]);
});

/**
 * The exact column is pinned alongside the line — but it is *not* the column of
 * the method name. The sniff registers on T_FUNCTION alone
 * (ConstructorNameSniff.php:43) and reports against that same, never-repointed
 * $stackPtr (ConstructorNameSniff.php:91), so the diagnostic lands on the
 * `function` keyword.
 *
 * On all three flagged lines the shape is `    public function <Name>()`: the
 * keyword starts at column 12 and the identifier not until column 21. Confirmed
 * by running vendor/bin/phpcs against this fixture, not inferred from the source.
 *
 * Pinned regardless of which token it is: a vendor change that repointed the
 * report at the name token would shift every column here and redden this test,
 * which is the signal worth holding.
 */
it('reports each violation at the function keyword token', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 18, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
        ['line' => 25, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
        ['line' => 37, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
    ]);
});

/**
 * The other two PHPMD mappings in CleanCode/ruleset.xml raise a warning to an error; this
 * one must not need to. Pinned so a vendor change to addWarning() fails here,
 * rather than leaving phpcs exiting 0 on a PHP4 constructor and quietly putting
 * phpmd back in the pipeline for this rule — the one thing issue #113 exists to
 * stop.
 */
it('reports PHP4 style constructors as errors without a severity override', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the shape where this ruleset is stricter than PHPMD: a PHP4-style
 * constructor in a namespaced class.
 *
 * PHPMD 2.15.0 reports nothing at all on this fixture, because its rule returns
 * early unless getNamespaceName() is '+global'. The behaviour is not
 * configurable on either side — the sniff exposes no property that scopes it to
 * the global namespace — so the divergence is documented and kept rather than
 * tuned away, per the issue's own fallback clause.
 */
it('is stricter than PHPMD on a namespaced class', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'namespaced-divergence.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        25 => [CONSTRUCTOR_NAME_OLD_STYLE],    // class NamespacedOldStyle
    ]);
});

/**
 * Pins the two shapes where PHPMD is stricter: an enum, and a class declaring
 * both __construct and a same-named method. PHPMD 2.15.0 reports lines 31 and
 * 43 of this fixture; this ruleset reports neither.
 *
 * The fixture stays in the global namespace so PHPMD's namespace guard cannot
 * be what produces its reports.
 */
it('stays silent on the shapes only PHPMD flags', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'phpmd-only-divergences.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Guards the test above from crediting CleanCode/ruleset.xml's exclude for that silence: the
 * same fixture, run through the unconfigured Generic standard, says nothing
 * either. The silence is the sniff's own — it registers on T_CLASS/T_ANON_CLASS
 * only, and suppresses OldStyle when the class already declares __construct.
 *
 * The excluded-codes fixture is asserted noisy in the same pass, so a Config
 * that silently failed to load the sniff could not fake this.
 */
it('stays silent on those shapes on the sniffs own account', function () use ($analyzeUnconfigured): void {
    $unconfigured = $analyzeUnconfigured('phpmd-only-divergences.php');

    expect(allViolationSourcesByLine($unconfigured))->toBe([]);

    $control = $analyzeUnconfigured('excluded-codes.php');

    expect(allViolationSourcesByLine($control))->not->toBe([]);
});

/**
 * PHPMD reports this rule rather than rewriting the code. Renaming a PHP4
 * constructor to __construct is only safe once every call site and every
 * subclass is known, which a single-file sniff cannot establish — so no
 * diagnostic may carry a fixer hook.
 *
 * The violation count is asserted first: an empty report also has zero fixable
 * violations, so without it silencing the sniff would make this pass.
 */
it('reports PHP4 style constructors without offering an auto-fix', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false]);
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
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3);

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('ConstructorNameSniff', 'failing.php')))
        ->and($file->getErrorCount())->toBe(3);
});

it('keeps the excluded call-site code silent through the master ruleset', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'excluded-codes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Guards the test above from passing vacuously: the same fixture, run through
 * the unconfigured Generic standard, must raise the code CleanCode/ruleset.xml excludes.
 * Without this, a fixture that trips nothing at all would look exactly like a
 * working exclude list.
 */
it('raises the excluded call-site code without our exclude', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('excluded-codes.php');

    expect(allViolationSourcesByLine($file))->toBe([
        33 => [CONSTRUCTOR_NAME_OLD_STYLE_CALL],    // parent::LegacyBase();
    ]);
});
