<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Classes.DisallowStaticMembers
 * sniff as wired into the master rules.xml (Classes: No Statics, issue #19).
 * Fixtures live in Fixtures/DisallowStaticMembersSniff/ beside this file.
 *
 * The sniff is detection-only, so there is no auto-fix assertion — instead the
 * tests prove every reported violation is non-fixable.
 */
class DisallowStaticMembersTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Classes.DisallowStaticMembers';

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

    public function testStaticMethodsAndPropertiesAreFlaggedAtTheirOwnLineAndColumn(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                ['line' => 9, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticProperty'],
                ['line' => 11, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticMethod'],
                ['line' => 21, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticMethod'],
                ['line' => 25, 'column' => 15, 'source' => self::SNIFF_CODE . '.StaticProperty'],
            ],
            $this->violations($file)
        );
    }

    public function testStaticMethodsAreFlaggedInEveryObjectOrientedContainer(): void
    {
        $file = $this->processFixture('containers.inc');

        // interface (12), abstract class (17), trait (22), enum (31). Line 17
        // also has a `static` return type on the same line — only the modifier
        // (column 21) is flagged, proving return types are not caught.
        $this->assertSame(
            [
                ['line' => 12, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticMethod'],
                ['line' => 17, 'column' => 21, 'source' => self::SNIFF_CODE . '.StaticMethod'],
                ['line' => 22, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticMethod'],
                ['line' => 31, 'column' => 12, 'source' => self::SNIFF_CODE . '.StaticMethod'],
            ],
            $this->violations($file)
        );
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        foreach (['violations.inc', 'containers.inc'] as $fixture) {
            $file = $this->processFixture($fixture);

            foreach ($file->getErrors() as $columns) {
                foreach ($columns as $messages) {
                    foreach ($messages as $message) {
                        $this->assertFalse($message['fixable'], $fixture . ' violations must be detection-only');
                    }
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

        $file = new LocalFile(__DIR__ . '/Fixtures/DisallowStaticMembersSniff/' . $fixture, $ruleset, $config);
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
