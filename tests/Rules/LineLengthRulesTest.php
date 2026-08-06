<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Rules;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Generic.Files.LineLength configuration in the master rules.xml:
 * warning above 100 characters, error above 120, reporting-only.
 *
 * Line numbers below refer to Fixtures/LineLengthViolations.inc, whose probe
 * lines are exactly 100 (line 3), 101 (line 5), 120 (line 7), and 121
 * (line 9) characters long.
 */
class LineLengthRulesTest extends TestCase
{
    private const WARNING_SOURCE = 'Generic.Files.LineLength.TooLong';

    private const ERROR_SOURCE = 'Generic.Files.LineLength.MaxExceeded';

    public function testCompliantFileRaisesNoLineLengthViolations(): void
    {
        $file = $this->processFixture('LineLengthCompliant.inc');

        // Scope to line-length sources only: the fixture runs through the
        // whole master ruleset, so unrelated PSR rules it happens to trip
        // (e.g. PSR1 side effects) must not mask what this test asserts —
        // that no line <=100 chars is flagged for length. Mirrors the
        // sibling ExceptionsRulesTest/CasingConventionsRulesetTest convention.
        $sources = array_merge(
            ...array_values($this->sourcesByLine($file->getWarnings())),
            ...array_values($this->sourcesByLine($file->getErrors())),
        );

        self::assertNotContains(self::WARNING_SOURCE, $sources, 'Compliant fixture must raise no line-length warning.');
        self::assertNotContains(self::ERROR_SOURCE, $sources, 'Compliant fixture must raise no line-length error.');
    }

    public function testLineOfExactlyOneHundredCharactersIsNotFlagged(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertArrayNotHasKey(3, $file->getWarnings());
        self::assertArrayNotHasKey(3, $file->getErrors());
    }

    public function testLineOfOneHundredOneCharactersRaisesWarning(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertSame([self::WARNING_SOURCE], $this->sourcesByLine($file->getWarnings())[5]);
        self::assertArrayNotHasKey(5, $file->getErrors());
    }

    public function testLineOfExactlyOneHundredTwentyCharactersRaisesWarningNotError(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertSame([self::WARNING_SOURCE], $this->sourcesByLine($file->getWarnings())[7]);
        self::assertArrayNotHasKey(7, $file->getErrors());
    }

    public function testLineOfOneHundredTwentyOneCharactersRaisesError(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertSame([self::ERROR_SOURCE], $this->sourcesByLine($file->getErrors())[9]);
        self::assertArrayNotHasKey(9, $file->getWarnings());
    }

    public function testViolationsAreReportedAtExpectedLinesOnly(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertSame(
            [5 => [self::WARNING_SOURCE], 7 => [self::WARNING_SOURCE]],
            $this->sourcesByLine($file->getWarnings())
        );
        self::assertSame([9 => [self::ERROR_SOURCE]], $this->sourcesByLine($file->getErrors()));
    }

    public function testRuleIsReportingOnly(): void
    {
        $file = $this->processFixture('LineLengthViolations.inc');

        self::assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->masterRulesetConfig();

        $file = new LocalFile(__DIR__ . '/Fixtures/' . $fixture, new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    private function masterRulesetConfig(): Config
    {
        // Pin installed_paths explicitly: the AbstractSniffUnitTest harness
        // blanks the static Config data (via ConfigDouble), which would
        // otherwise silently deregister the third-party standards the master
        // ruleset references, breaking the rules.xml parse here.
        Config::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        // The argv must be non-empty: Config falls back to parsing the live
        // $_SERVER['argv'] as PHPCS flags when given none, which would leak
        // unrelated PHPUnit arguments (e.g. --filter) into the shared Config.
        $config = new Config(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);
        $config->cache = false;

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

        return $sources;
    }
}
