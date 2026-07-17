<?php

declare(strict_types=1);

namespace MikeBronner\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the SlevomatCodingStandard.Namespaces.UnusedUses rule
 * as configured in the master rules.xml (Use Statements: No Unused Entries,
 * issue #68). Fixtures live in the fixtures/ directory beside this file.
 */
class UnusedUsesTest extends TestCase
{
    private const SNIFF_CODE = 'SlevomatCodingStandard.Namespaces.UnusedUses';

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('UnusedUsesCompliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEachUnusedUseIsFlaggedIndividuallyAtItsOwnLine(): void
    {
        $file = $this->processFixture('UnusedUsesViolations.inc');
        $errors = $file->getErrors();

        $this->assertSame([7, 10], array_keys($errors));

        foreach ([7, 10] as $line) {
            $lineErrors = array_merge(...array_values($errors[$line]));

            $this->assertCount(1, $lineErrors);
            $this->assertSame(self::SNIFF_CODE . '.UnusedUse', $lineErrors[0]['source']);
            $this->assertTrue($lineErrors[0]['fixable']);
        }
    }

    public function testUseReferencedOnlyInDocblockIsNotUnused(): void
    {
        $file = $this->processFixture('UnusedUsesDocblockOnly.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testAutoFixRemovesOnlyTheUnusedUseStatements(): void
    {
        $file = $this->processFixture('UnusedUsesViolations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/fixtures/UnusedUsesViolations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <properties> configured there.
        // populateTokenListeners() re-applies those properties.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . '/fixtures/' . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    private function createConfig(): ConfigDouble
    {
        $config = new ConfigDouble();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble blanks CodeSniffer.conf, which is where Composer
        // registers Slevomat's installed path — restore it (in memory only)
        // so the ruleset can resolve the SlevomatCodingStandard sniffs.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }
}
