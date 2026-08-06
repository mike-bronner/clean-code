<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Rules;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the binary-operator and concatenation spacing configured in the master
 * rules.xml for "Arrays: Operator spacing & line breaks" (#35): the bundled
 * Squiz.WhiteSpace.OperatorSpacing and Squiz.Strings.ConcatenationSpacing
 * sniffs enforce exactly one space on each side. It also pins that both custom
 * CleanCode.Operators.* sniffs are reachable through the master ruleset.
 *
 * The two spacing sniffs are isolated (loaded from rules.xml with their
 * configured properties, then $ruleset->sniffs narrowed to them) so the
 * behaviour and autofix assertions are unaffected by sibling standards.
 *
 * Fixtures live in Fixtures/OperatorSpacing/; the probe lines in
 * violations.inc are line 5 (arithmetic), 6 (comparison), 7 (concatenation),
 * 8 (extra-padded arithmetic) and 9 (extra-padded assignment — exercises
 * ignoreSpacingBeforeAssignments="false"). wrapped.inc probes ignoreNewlines.
 */
class OperatorSpacingRulesTest extends TestCase
{
    private const OPERATOR_SPACING = 'Squiz.WhiteSpace.OperatorSpacing';

    private const CONCAT_SPACING = 'Squiz.Strings.ConcatenationSpacing';

    private const NOT_OPERATOR = 'CleanCode.Operators.NotOperatorSpacing';

    private const LINE_BREAK = 'CleanCode.Operators.OperatorLineBreak';

    private const FIXTURE_DIR = '/Fixtures/OperatorSpacing/';

    public function testConfiguredSpacingSniffsAreRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::OPERATOR_SPACING, $ruleset->sniffCodes);
        $this->assertArrayHasKey(self::CONCAT_SPACING, $ruleset->sniffCodes);
    }

    public function testCustomOperatorSniffsAreReachableThroughMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::NOT_OPERATOR, $ruleset->sniffCodes);
        $this->assertArrayHasKey(self::LINE_BREAK, $ruleset->sniffCodes);
    }

    public function testCompliantFileRaisesNoSpacingViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testMissingAndExtraSpacingIsFlaggedAtExpectedLines(): void
    {
        $byLine = $this->sourcesByLine($this->processFixture('violations.inc')->getErrors());

        $this->assertSame([5, 6, 7, 8, 9], array_keys($byLine));

        foreach ([5, 6, 8, 9] as $line) {
            foreach ($byLine[$line] as $source) {
                $this->assertStringStartsWith(self::OPERATOR_SPACING, $source);
            }
        }
    }

    /**
     * ignoreSpacingBeforeAssignments="false" makes the sniff police the space
     * before "=" too, so alignment padding (`$e  = 1;`, line 9) is flagged.
     * With the property at Squiz's default that line is silent — this pins the
     * one property #35 adds to the sniff.
     */
    public function testExtraSpaceBeforeAssignmentIsFlagged(): void
    {
        $byLine = $this->sourcesByLine($this->processFixture('violations.inc')->getErrors());

        $this->assertSame(
            [self::OPERATOR_SPACING . '.SpacingBefore'],
            $byLine[9] ?? []
        );
    }

    /**
     * ignoreNewlines="true" keeps both spacing sniffs silent on an operator
     * that leads a wrapped continuation line — the very layout the line-break
     * standard mandates. Without it they would flag the operator-led lines
     * ("Expected 1 space before …; newline found").
     */
    public function testSpacingSniffsStaySilentOnOperatorLedContinuationLines(): void
    {
        $file = $this->processFixture('wrapped.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testConcatenationSpacingIsEnforcedToOneSpace(): void
    {
        $byLine = $this->sourcesByLine($this->processFixture('violations.inc')->getErrors());

        foreach ($byLine[7] as $source) {
            $this->assertStringStartsWith(self::CONCAT_SPACING, $source);
        }
    }

    public function testSpacingViolationsAutoFixToOneSpaceEachSide(): void
    {
        $file = $this->processFixture('autofix-before.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . self::FIXTURE_DIR . 'autofix-after.inc',
            $file->fixer->getContents()
        );
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the two configured spacing sniffs after the full ruleset has
        // loaded them with their rules.xml properties. A $config->sniffs
        // restriction would make Ruleset skip parsing rules.xml (under
        // PHP_CODESNIFFER_IN_TESTS), dropping those configured properties.
        $isolated = [];

        foreach ([self::OPERATOR_SPACING, self::CONCAT_SPACING] as $code) {
            $class = $ruleset->sniffCodes[$code];
            $isolated[$class] = $ruleset->sniffs[$class];
        }

        $ruleset->sniffs = $isolated;
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

        // ConfigDouble blanks CodeSniffer.conf, where Composer registers
        // the third-party standards' installed paths; the master ruleset
        // references them, so restore them (in memory only) for the
        // rules.xml parse.
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
