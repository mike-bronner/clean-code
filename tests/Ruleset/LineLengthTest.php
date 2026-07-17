<?php

/**
 * Tests the Generic.Files.LineLength configuration in the master rules.xml:
 * warning above 100 characters, error above 120, reporting-only.
 *
 * Line numbers below refer to fixtures/LineLengthViolations.inc, whose probe
 * lines are exactly 100 (line 3), 101 (line 5), 120 (line 7), and 121
 * (line 9) characters long.
 */

declare(strict_types=1);

namespace MikeBronner\PhpcsRules\Tests\Ruleset;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

class LineLengthTest extends TestCase
{
    private const WARNING_SOURCE = 'Generic.Files.LineLength.TooLong';
    private const ERROR_SOURCE = 'Generic.Files.LineLength.MaxExceeded';

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('LineLengthCompliant.inc');

        self::assertSame(0, $file->getErrorCount());
        self::assertSame(0, $file->getWarningCount());
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
        $config = new Config(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);
        $ruleset = new Ruleset($config);
        $file = new LocalFile(__DIR__ . '/fixtures/' . $fixture, $ruleset, $config);
        $file->process();

        return $file;
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
