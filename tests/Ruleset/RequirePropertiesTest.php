<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Classes.RequireProperties sniff as
 * wired into the master rules.xml (Properties: Are Required, issue #55).
 * Fixtures live in Fixtures/RequireProperties/ beside this file.
 *
 * The sniff is detection-only, so there is no auto-fix assertion — instead the
 * tests prove every reported violation is non-fixable.
 */
class RequirePropertiesTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Classes.RequireProperties';

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

    public function testClassesWithoutPropertiesAreFlaggedAtTheirDeclarationLine(): void
    {
        $file = $this->processFixture('violations.inc');

        // A methods-only class (10), an empty stub class (18), and a class
        // whose only property belongs to a nested anonymous class (25) each
        // lack state of their own and are flagged at the `class` keyword. The
        // plain constructor and method parameters on line 12 are not
        // properties and must not satisfy the rule.
        $this->assertSame(
            [
                ['line' => 10, 'column' => 1, 'source' => self::SNIFF_CODE . '.MissingProperty'],
                ['line' => 18, 'column' => 1, 'source' => self::SNIFF_CODE . '.MissingProperty'],
                ['line' => 25, 'column' => 1, 'source' => self::SNIFF_CODE . '.MissingProperty'],
            ],
            $this->violations($file)
        );
    }

    public function testInterfacesTraitsAndEnumsAreNeverFlagged(): void
    {
        $file = $this->processFixture('excluded.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        foreach ($file->getErrors() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $this->assertFalse($message['fixable'], 'RequireProperties violations must be detection-only');
                }
            }
        }
    }

    /**
     * Flattens a processed file's errors into an ordered list of
     * line/column/source tuples for exact assertion.
     *
     * @return array<int, array{line: int, column: int, source: string}>
     */
    private function violations(LocalFile $file): array
    {
        $flat = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $column => $messages) {
                foreach ($messages as $message) {
                    $flat[] = ['line' => $line, 'column' => $column, 'source' => $message['source']];
                }
            }
        }

        usort($flat, static fn (array $a, array $b): int => [$a['line'], $a['column']] <=> [$b['line'], $b['column']]);

        return $flat;
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

        $file = new LocalFile(__DIR__ . '/Fixtures/RequireProperties/' . $fixture, $ruleset, $config);
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
