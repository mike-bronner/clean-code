<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Operators.OperatorLineBreak sniff (Arrays:
 * Operator spacing & line breaks, #35). Fixtures live in
 * Fixtures/OperatorLineBreakSniff/ beside this file.
 *
 * This rule is report-only — where the operator lands on the rewritten line is
 * a layout judgement — so there are no autofix-before/autofix-after fixtures;
 * testViolationsAreNotAutoFixable pins that decision.
 *
 * The sniff is isolated from the rest of the master ruleset so these
 * assertions stay stable as sibling standards land in rules.xml.
 */
class OperatorLineBreakTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Operators.OperatorLineBreak';

    private const FIXTURE_DIR = '/Fixtures/OperatorLineBreakSniff/';

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEveryDanglingOperatorIsFlaggedAtItsLine(): void
    {
        $file = $this->processFixture('failing.inc');

        // Logical (&&, line 5), concatenation (., lines 8/12/22), assignment
        // (=, line 16) and comparison (===, line 19) operators left dangling
        // at the end of a wrapped line. Line 22 dangles behind a trailing
        // comment — flagged because the sniff skips comments when locating the
        // next code token.
        $this->assertSame([5, 8, 12, 16, 19, 22], array_keys($this->sourcesByLine($file->getErrors())));

        foreach ($this->sourcesByLine($file->getErrors()) as $sources) {
            $this->assertSame([self::SNIFF_CODE . '.OperatorAtLineEnd'], $sources);
        }
    }

    /**
     * Operators dangling inside an if/elseif/while/for condition are owned by
     * CleanCode.Conditionals.OneConditionPerLine (which can auto-fix them), so
     * this sniff defers — otherwise the same wrap is reported twice. Both the
     * logical `||` and the comparison `===` in the fixture would flag without
     * the deferral.
     */
    public function testDanglingOperatorsInsideConditionsAreDeferred(): void
    {
        $file = $this->processFixture('deferred-conditional.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * A non-boolean operator (comparison, assignment, concatenation) dangling
     * inside a *multi*-condition control structure is owned by neither this
     * sniff's old blanket deferral nor OneConditionPerLine (which polices only
     * boolean-operator placement there), so it must be reported here. Covers
     * the top-level case (=== / . beside a top-level ||/&&) and the nested case
     * (=== inside an inner grouping parenthesis).
     */
    public function testDanglingNonBooleanOperatorInMultiConditionIsReported(): void
    {
        $file = $this->processFixture('reported-conditional.inc');

        $this->assertSame([9, 19, 29], array_keys($this->sourcesByLine($file->getErrors())));

        foreach ($this->sourcesByLine($file->getErrors()) as $sources) {
            $this->assertSame([self::SNIFF_CODE . '.OperatorAtLineEnd'], $sources);
        }
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertGreaterThan(0, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it —
        // see NotOperatorSpacingTest for why $config->sniffs cannot be used.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . self::FIXTURE_DIR . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    private function createConfig(): ConfigDouble
    {
        $config = new ConfigDouble();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        ConfigDouble::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        return $config;
    }

    /**
     * Collapses PHPCS's line => column => violations structure to a map of
     * line number => list of violation source codes.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, string>>
     */
    private function sourcesByLine(array $messages): array
    {
        $sources = [];

        foreach ($messages as $line => $columns) {
            foreach ($columns as $violations) {
                foreach ($violations as $violation) {
                    $sources[$line][] = $violation['source'];
                }
            }
        }

        ksort($sources);

        return $sources;
    }
}
