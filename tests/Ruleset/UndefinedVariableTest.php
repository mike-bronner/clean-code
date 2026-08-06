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

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEachUndefinedReadIsFlaggedAtItsOwnLine(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                11 => [self::UNDEFINED],          // return $undefinedScalar;
                16 => [self::UNDEFINED],          // return $undefinedArray['key'];
                21 => [self::UNDEFINED],          // "value: {$undefinedInString}"
                26 => [self::UNDEFINED],          // read one line above its assignment
                34 => [self::UNDEFINED_UNSET],    // unset($neverAssigned);
            ],
            $this->sourceMap($file->getWarnings())
        );
    }

    /**
     * PHPMD reports this rule rather than rewriting the code, and an undefined
     * variable has no machine-derivable value to substitute — so every
     * diagnostic must be an unfixable warning, never an error or a fixer hook.
     */
    public function testViolationsAreUnfixableWarnings(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame([], $file->getErrors());

        foreach ($file->getWarnings() as $line => $columns) {
            foreach (array_merge(...array_values($columns)) as $warning) {
                $this->assertFalse($warning['fixable'], 'Fixable warning on line ' . $line);
            }
        }
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
