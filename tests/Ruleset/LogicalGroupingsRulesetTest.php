<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Indentation.LogicalGroupings sniff
 * as wired into the master rules.xml (Indentation: Logical Groupings, #41).
 * Fixtures live in Fixtures/LogicalGroupings/ beside this file.
 *
 * Per the repo-wide convention the sniff is driven through the real ruleset —
 * not AbstractSniffUnitTest — with separate passing (compliant.inc) and failing
 * (violations.inc) fixtures, and separate autofix input (autofix-before.inc)
 * and expected output (autofix-after.inc).
 */
class LogicalGroupingsRulesetTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Indentation.LogicalGroupings';

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

    public function testViolationsAreFlaggedAtTheExactLineAndColumn(): void
    {
        $file = $this->processFixture('violations.inc');

        // A group indented too shallow, exactly to the enclosing level, or too
        // deep all flag its first condition as GroupNotIndented; every later
        // condition off the group's level flags as MisalignedGroupedCondition.
        // The nested case (lines 68-69) proves the inner group is measured
        // against its own parent — one level deeper again.
        $this->assertSame(
            [
                ['line' => 14, 'column' => 13, 'source' => self::SNIFF_CODE . '.GroupNotIndented'],
                ['line' => 15, 'column' => 13, 'source' => self::SNIFF_CODE . '.MisalignedGroupedCondition'],
                ['line' => 27, 'column' => 15, 'source' => self::SNIFF_CODE . '.GroupNotIndented'],
                ['line' => 28, 'column' => 15, 'source' => self::SNIFF_CODE . '.MisalignedGroupedCondition'],
                ['line' => 40, 'column' => 21, 'source' => self::SNIFF_CODE . '.GroupNotIndented'],
                ['line' => 41, 'column' => 21, 'source' => self::SNIFF_CODE . '.MisalignedGroupedCondition'],
                ['line' => 54, 'column' => 19, 'source' => self::SNIFF_CODE . '.MisalignedGroupedCondition'],
                ['line' => 68, 'column' => 17, 'source' => self::SNIFF_CODE . '.GroupNotIndented'],
                ['line' => 69, 'column' => 17, 'source' => self::SNIFF_CODE . '.MisalignedGroupedCondition'],
            ],
            $this->violations($file)
        );
    }

    public function testFixerReindentsGroupedConditionsToTheCorrectNestingLevel(): void
    {
        $file = $this->processFixture('autofix-before.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/LogicalGroupings/autofix-after.inc',
            $file->fixer->getContents()
        );
    }

    public function testFixedFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('autofix-after.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
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

        $file = new LocalFile(__DIR__ . '/Fixtures/LogicalGroupings/' . $fixture, $ruleset, $config);
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
