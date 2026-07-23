<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Operators.ManipulationOperatorPlacement
 * sniff as wired into the master rules.xml (Operators: Manipulative, issue #59).
 * Fixtures live in Fixtures/ManipulationOperatorPlacement/ beside this file.
 *
 * Each test isolates the sniff under test on a ruleset built from rules.xml, so
 * sibling standards wired into the shared master ruleset cannot mask the
 * violations asserted here nor alter the fixer's output.
 */
class ManipulationOperatorPlacementTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Operators.ManipulationOperatorPlacement';

    private const VIOLATION_SOURCE = self::SNIFF_CODE . '.OperatorNotLeading';

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testUnaryAndReferenceFormsAreNotFlaggedEvenWhenWrapped(): void
    {
        $file = $this->processFixture('unary.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testTrailingOperatorsAreFlaggedAtTheirExactLineAndColumn(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                ['line' => 5, 'column' => 13, 'source' => self::VIOLATION_SOURCE],
                ['line' => 8, 'column' => 19, 'source' => self::VIOLATION_SOURCE],
                ['line' => 11, 'column' => 15, 'source' => self::VIOLATION_SOURCE],
                ['line' => 12, 'column' => 15, 'source' => self::VIOLATION_SOURCE],
                ['line' => 15, 'column' => 20, 'source' => self::VIOLATION_SOURCE],
                ['line' => 16, 'column' => 17, 'source' => self::VIOLATION_SOURCE],
                ['line' => 19, 'column' => 15, 'source' => self::VIOLATION_SOURCE],
                ['line' => 20, 'column' => 12, 'source' => self::VIOLATION_SOURCE],
                ['line' => 23, 'column' => 17, 'source' => self::VIOLATION_SOURCE],
                ['line' => 24, 'column' => 10, 'source' => self::VIOLATION_SOURCE],
                ['line' => 25, 'column' => 13, 'source' => self::VIOLATION_SOURCE],
                ['line' => 28, 'column' => 16, 'source' => self::VIOLATION_SOURCE],
                ['line' => 31, 'column' => 15, 'source' => self::VIOLATION_SOURCE],
                ['line' => 34, 'column' => 20, 'source' => self::VIOLATION_SOURCE],
            ],
            $this->violations($file)
        );
    }

    public function testEveryTrailingOperatorViolationIsAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertGreaterThan(0, $file->getErrorCount());
        $this->assertSame($file->getErrorCount(), $file->getFixableCount());
    }

    public function testFixerMovesEachTrailingOperatorToLeadTheContinuationLine(): void
    {
        $file = $this->processFixture('violations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/ManipulationOperatorPlacement/violations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testFixedFixturePassesTheSniffWithZeroViolations(): void
    {
        $file = $this->processFixture('violations.inc.fixed');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testOperandBoundaryTrailingOperatorsAreFlaggedAtTheirExactLineAndColumn(): void
    {
        $file = $this->processFixture('operand-boundaries.inc');

        // A `+`/`-` trailing a magic constant, an interpolated string, or a
        // heredoc/nowdoc body is a binary operator, not a unary sign — each of
        // those left operands must be recognised so the trailing operator is
        // flagged rather than silently exempted.
        $this->assertSame(
            [
                ['line' => 9, 'column' => 19, 'source' => self::VIOLATION_SOURCE],
                ['line' => 12, 'column' => 26, 'source' => self::VIOLATION_SOURCE],
                ['line' => 17, 'column' => 9, 'source' => self::VIOLATION_SOURCE],
                ['line' => 22, 'column' => 9, 'source' => self::VIOLATION_SOURCE],
            ],
            $this->violations($file)
        );
    }

    public function testOperandBoundaryViolationsAutoFixToLeadTheContinuationLine(): void
    {
        $file = $this->processFixture('operand-boundaries.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/ManipulationOperatorPlacement/operand-boundaries.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testCommentBetweenOperandsIsReportedButNotAutoFixable(): void
    {
        $file = $this->processFixture('comment.inc');

        $this->assertSame(
            [['line' => 7, 'column' => 13, 'source' => self::VIOLATION_SOURCE]],
            $this->violations($file)
        );
        $this->assertSame(1, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
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

        $file = new LocalFile(
            __DIR__ . '/Fixtures/ManipulationOperatorPlacement/' . $fixture,
            $ruleset,
            $config
        );
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
