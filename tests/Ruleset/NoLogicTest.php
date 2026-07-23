<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Constructors.NoLogic sniff as wired
 * into the master rules.xml (Constructors: No Logic in Constructors, issue #40).
 * Fixtures live in Fixtures/NoLogic/ beside this file.
 *
 * The sniff is detection-only, so there is no auto-fix assertion — instead the
 * tests prove every reported violation is non-fixable.
 */
class NoLogicTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Constructors.NoLogic';

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

    public function testGlobalFunctionNamedConstructIsNotInspected(): void
    {
        $file = $this->processFixture('global-construct.inc');

        // A free `function __construct()` is not a class constructor; the
        // OO-scope guard must skip it entirely, so its logic-bearing body
        // produces no violations.
        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEveryNonAssignmentStatementIsFlaggedAtItsOwnLine(): void
    {
        $file = $this->processFixture('violations.inc');

        // Compliant statements in the same fixture — the top-level assignment on
        // line 12 and every statement nested inside a flagged control structure
        // — are absent from this list, proving they are not flagged.
        $source = self::SNIFF_CODE . '.LogicFound';

        $this->assertSame(
            [
                ['line' => 13, 'column' => 9, 'source' => $source], // if … elseif … else
                ['line' => 20, 'column' => 9, 'source' => $source], // for
                ['line' => 23, 'column' => 9, 'source' => $source], // foreach
                ['line' => 26, 'column' => 9, 'source' => $source], // while
                ['line' => 29, 'column' => 9, 'source' => $source], // do … while
                ['line' => 32, 'column' => 9, 'source' => $source], // switch
                ['line' => 36, 'column' => 9, 'source' => $source], // try … catch … finally
                ['line' => 43, 'column' => 9, 'source' => $source], // method call
                ['line' => 44, 'column' => 9, 'source' => $source], // function call
                ['line' => 45, 'column' => 9, 'source' => $source], // local-variable assignment
                ['line' => 46, 'column' => 9, 'source' => $source], // increment
                ['line' => 47, 'column' => 9, 'source' => $source], // call in assignment target
                ['line' => 48, 'column' => 9, 'source' => $source], // call in subscript index
                ['line' => 49, 'column' => 9, 'source' => $source], // chained call then subscript
                ['line' => 50, 'column' => 9, 'source' => $source], // throw
            ],
            $this->violations($file)
        );
    }

    public function testControlStructureChainsAreReportedOnceNotPerClause(): void
    {
        $file = $this->processFixture('violations.inc');

        $lines = array_column($this->violations($file), 'line');

        // `if … elseif … else` opens on line 13; the `elseif` (15) and `else`
        // (17) clauses carry no separate violation.
        $this->assertSame([13], array_values(array_filter($lines, static fn (int $l): bool => $l === 13)));
        $this->assertNotContains(15, $lines);
        $this->assertNotContains(17, $lines);

        // `do … while` opens on line 29; its `while (...)` condition tail (31)
        // carries no separate violation.
        $this->assertSame([29], array_values(array_filter($lines, static fn (int $l): bool => $l === 29)));
        $this->assertNotContains(31, $lines);

        // `try … catch … finally` opens on line 36; the `catch` (38) and
        // `finally` (40) clauses carry no separate violation.
        $this->assertSame([36], array_values(array_filter($lines, static fn (int $l): bool => $l === 36)));
        $this->assertNotContains(38, $lines);
        $this->assertNotContains(40, $lines);
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        foreach ($file->getErrors() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $this->assertFalse($message['fixable'], 'NoLogic violations must be detection-only');
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

        $file = new LocalFile(__DIR__ . '/Fixtures/NoLogic/' . $fixture, $ruleset, $config);
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
