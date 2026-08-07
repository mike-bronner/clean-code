<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the VariableAnalysis.CodeAnalysis.VariableAnalysis rule
 * as configured in the master rules.xml, replacing PHPMD's CleanCode
 * UndefinedVariable rule (issue #85). Fixtures live in
 * Fixtures/UndefinedVariable/ beside this file.
 *
 * The sniff emits five codes; rules.xml keeps the two that mean "a variable is
 * read before it is defined" and excludes the other three, which belong to
 * other PHPMD rules or to none. Both halves are pinned below: the excluded
 * codes stay silent through rules.xml, and the same fixture proves they would
 * fire without the excludes — so dropping an <exclude> fails this suite.
 *
 * Fixtures follow the naming CONTRIBUTING.md prescribes — passing.inc and
 * failing.inc, never one shared file — plus two extra files for shapes that
 * belong to neither set:
 *
 * - passing.inc — code the rule must stay silent on.
 * - failing.inc — the parity set. PHPMD 2.15.0 and this ruleset flag the
 *   same lines.
 * - divergences.inc — where they differ, plus the shape neither tool catches.
 *   Pinned by a test so the gap cannot drift back into an unearned parity
 *   claim; described in docs/standards/phpmd-clean-code-undefined-variable.md.
 * - excluded-codes.inc — the four sniff codes rules.xml excludes.
 *
 * There is no autofix-after.inc: the rule is not fixable, and
 * testTheFixerLeavesFailingFixtureByteIdentical proves it by running the real
 * fixer rather than asserting the absence.
 */
class UndefinedVariableTest extends TestCase
{
    private const SNIFF_CODE = 'VariableAnalysis.CodeAnalysis.VariableAnalysis';

    private const UNDEFINED = self::SNIFF_CODE . '.UndefinedVariable';

    private const UNDEFINED_UNSET = self::SNIFF_CODE . '.UndefinedUnsetVariable';

    private const EXCLUDED_CODES = [
        self::SNIFF_CODE . '.UnusedVariable',
        self::SNIFF_CODE . '.VariableRedeclaration',
        self::SNIFF_CODE . '.SelfOutsideClass',
        self::SNIFF_CODE . '.StaticOutsideClass',
    ];

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testPassingFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * The parity set: PHPMD 2.15.0, run with only UndefinedVariable enabled,
     * reports these same four lines on this same fixture and nothing else.
     */
    public function testEachUndefinedReadIsFlaggedAtItsOwnLine(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                17 => [self::UNDEFINED],          // return $undefinedScalar;
                22 => [self::UNDEFINED],          // return $undefinedArray['key'];
                27 => [self::UNDEFINED],          // "value: {$undefinedInString}"
                32 => [self::UNDEFINED_UNSET],    // unset($neverAssigned);
            ],
            $this->sourceMap($file->getWarnings())
        );
    }

    /**
     * Pins the two shapes where this ruleset is stricter than PHPMD, and — by
     * asserting the whole map — the one shape neither tool reports.
     *
     * PHPMD 2.15.0 reports nothing at all on this fixture. This ruleset
     * reports exactly two lines. Line 75, the conditionally-assigned read, is
     * absent from both: it would appear in this map if the sniff caught it.
     *
     * Neither behaviour is configurable — VariableAnalysisSniff exposes no
     * property that toggles statement-order or closure scoping — so the gap is
     * documented rather than tuned away, per the issue's own fallback clause.
     */
    public function testDivergencesFromPhpmdAreFlaggedExactlyWhereRecorded(): void
    {
        $file = $this->processFixture('divergences.inc');

        $this->assertSame(
            [
                34 => [self::UNDEFINED],    // read one line above its assignment
                56 => [self::UNDEFINED],    // closure local read outside the closure
            ],
            $this->sourceMap($file->getWarnings())
        );
    }

    /**
     * Guards the test above from crediting our own <exclude>s for the silence
     * on line 75: the same fixture, run through the unconfigured
     * VariableAnalysis standard, still says nothing about that line. The two
     * divergence lines are asserted present in the same pass, so a fixture
     * that stopped parsing could not fake this.
     */
    public function testTheConditionalBlindSpotIsTheSniffsOwnBehaviour(): void
    {
        $file = $this->processFixture('divergences.inc', 'VariableAnalysis');

        $flaggedLines = array_keys($this->sourceMap($file->getWarnings()));

        $this->assertContains(34, $flaggedLines);
        $this->assertContains(56, $flaggedLines);
        $this->assertNotContains(75, $flaggedLines);
    }

    /**
     * PHPMD reports this rule rather than rewriting the code, and an undefined
     * variable has no machine-derivable value to substitute — so every
     * diagnostic must be an unfixable warning, never an error or a fixer hook.
     */
    public function testViolationsAreUnfixableWarnings(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertNotSame([], $file->getWarnings());

        foreach ($file->getWarnings() as $line => $columns) {
            foreach (array_merge(...array_values($columns)) as $warning) {
                $this->assertFalse($warning['fixable'], 'Fixable warning on line ' . $line);
            }
        }
    }

    /**
     * The "after-fixed" half of the fixture contract, for a rule that has no
     * autofix-after.inc to compare against: the fixer's real output on
     * failing.inc *is* failing.inc, byte for byte.
     *
     * Proven by driving the same `Fixer` phpcbf drives, not by trusting the
     * `fixable` flag the test above reads. The warning count is asserted both
     * before and after the run, so a fixture that stopped tripping the sniff —
     * or a fixer that silently swallowed every diagnostic — cannot pass this
     * vacuously.
     */
    public function testTheFixerLeavesFailingFixtureByteIdentical(): void
    {
        $fixture = __DIR__ . '/Fixtures/UndefinedVariable/failing.inc';

        $file = $this->processFixture('failing.inc');

        $this->assertCount(4, $file->getWarnings());

        $file->fixer->fixFile();

        $this->assertStringEqualsFile($fixture, $file->fixer->getContents());
        $this->assertCount(4, $file->getWarnings());
    }

    public function testExcludedCodesStaySilentThroughTheMasterRuleset(): void
    {
        $file = $this->processFixture('excluded-codes.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Guards the test above from passing vacuously: the same fixture, run
     * through the unconfigured VariableAnalysis standard, must raise every code
     * rules.xml excludes. Without this, a fixture that trips nothing at all
     * would look like a working exclude list.
     */
    public function testExcludedCodesFireWithoutTheMasterRulesetExcludes(): void
    {
        $file = $this->processFixture('excluded-codes.inc', 'VariableAnalysis');

        $raised = array_merge(
            ...array_values($this->sourceMap($file->getErrors())),
            ...array_values($this->sourceMap($file->getWarnings()))
        );

        foreach (self::EXCLUDED_CODES as $code) {
            $this->assertContains($code, $raised);
        }
    }

    /**
     * @param array<int, array<int, array<int, array<string, mixed>>>> $violations
     *
     * @return array<int, array<int, string>> line => violation source codes
     */
    private function sourceMap(array $violations): array
    {
        $map = [];

        foreach ($violations as $line => $columns) {
            foreach (array_merge(...array_values($columns)) as $violation) {
                $map[$line][] = $violation['source'];
            }
        }

        ksort($map);

        return $map;
    }

    private function processFixture(string $fixture, ?string $standard = null): LocalFile
    {
        $config = $this->createConfig($standard);
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <exclude>s configured there.
        // populateTokenListeners() re-applies the remaining rules.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(
            __DIR__ . '/Fixtures/UndefinedVariable/' . $fixture,
            $ruleset,
            $config
        );
        $file->process();

        return $file;
    }

    /**
     * @param string|null $standard the standard to load, defaulting to the
     *                              master ruleset under test
     */
    private function createConfig(?string $standard = null): ConfigDouble
    {
        $config = new ConfigDouble();
        $config->cache = false;
        $config->standards = [$standard ?? dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble blanks CodeSniffer.conf, which is where Composer
        // registers the third-party standards' installed paths — restore them
        // (in memory only) so the ruleset can resolve their sniffs.
        ConfigDouble::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        return $config;
    }
}
