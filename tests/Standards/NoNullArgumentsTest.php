<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Methods.NoNullArguments sniff (Methods: No Null
 * Arguments, #71). Fixtures live in Fixtures/NoNullArgumentsSniff/ beside this
 * file: separate passing and failing files, and separate
 * autofix-before/autofix-after files.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class NoNullArgumentsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Methods.NoNullArguments';

    private const VIOLATION = self::SNIFF_CODE . '.PositionalNull';

    private const FIXTURE_DIR = '/Fixtures/NoNullArgumentsSniff/';

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

    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                // $this-> method call
                36 => [self::VIOLATION],
                37 => [self::VIOLATION],
                // ClassName:: and self:: static calls
                40 => [self::VIOLATION],
                41 => [self::VIOLATION],
                // new ClassName() and new self() constructor calls
                44 => [self::VIOLATION],
                45 => [self::VIOLATION],
                // two null arguments in one call — one violation each
                48 => [self::VIOLATION, self::VIOLATION],
                // null before a further positional argument
                52 => [self::VIOLATION],
                // null before an argument that cannot be named — reported,
                // but not fixable
                57 => [self::VIOLATION],
                60 => [self::VIOLATION],
                64 => [self::VIOLATION],
                // standalone function call
                86 => [self::VIOLATION],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    public function testViolationsAreFixableExceptWhereALaterArgumentCannotBeNamed(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(13, $file->getErrorCount());

        // Lines 57, 60 and 64 are reported but cannot be rewritten — a later
        // argument in each is a spread or lands in a variadic parameter, so
        // they are excluded from the fixable count.
        $this->assertSame(10, $file->getFixableCount());
    }

    /**
     * @testWith [57]
     *           [60]
     *           [64]
     */
    public function testTheUnfixableViolationExplainsWhy(int $line): void
    {
        $message = $this->firstErrorOnLine($this->processFixture('failing.inc'), $line);

        $this->assertFalse($message['fixable']);
        $this->assertStringContainsString('cannot be fixed automatically', $message['message']);
    }

    public function testViolationMessageNamesTheParameterToUse(): void
    {
        $message = $this->firstErrorOnLine($this->processFixture('failing.inc'), 86);

        $this->assertTrue($message['fixable']);
        $this->assertStringContainsString('$retries', $message['message']);
        $this->assertStringContainsString('retries: null', $message['message']);
    }

    public function testAutoFixProducesTheExpectedOutput(): void
    {
        $file = $this->processFixture('autofix-before.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . self::FIXTURE_DIR . 'autofix-after.inc',
            $file->fixer->getContents()
        );
    }

    /**
     * Re-running the sniff over its own output must surface nothing new: every
     * violation the fixer touched is gone, and the only report left is the one
     * it deliberately declined to fix (the trailing-spread call on line 30).
     */
    public function testTheFixedOutputIsCompliantExceptWhereTheFixerDeclined(): void
    {
        $file = $this->processFixture('autofix-after.inc');

        $this->assertSame([30 => [self::VIOLATION]], $this->sourcesByLine($file->getErrors()));
        $this->assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it.
        // A $config->sniffs restriction cannot be used: under
        // PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip parsing rules.xml,
        // which is what pulls the custom CleanCode sniffs in.
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

        // ConfigDouble blanks CodeSniffer.conf, where Composer registers
        // Slevomat's installed path; the master ruleset references Slevomat,
        // so restore it (in memory only) for the rules.xml parse.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }

    /**
     * Returns the first violation reported on $line.
     *
     * @return array<string, mixed>
     */
    private function firstErrorOnLine(LocalFile $file, int $line): array
    {
        $errors = $file->getErrors();

        $this->assertArrayHasKey($line, $errors);

        return array_values(array_values($errors[$line])[0])[0];
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
