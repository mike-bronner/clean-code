<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Collections.OnlyUseCollectionMethods sniff
 * (Collections: Only Use Collection Methods, #28). Fixtures live in
 * Fixtures/OnlyUseCollectionMethodsSniff/ beside this file: separate passing
 * and failing files, and separate autofix-before/autofix-after files.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class OnlyUseCollectionMethodsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Collections.OnlyUseCollectionMethods';

    private const FIXTURE_DIR = '/Fixtures/OnlyUseCollectionMethodsSniff/';

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
                12 => [self::SNIFF_CODE . '.Found'],
                13 => [self::SNIFF_CODE . '.Found'],
                14 => [self::SNIFF_CODE . '.Found'],
                15 => [self::SNIFF_CODE . '.Found'],
                16 => [self::SNIFF_CODE . '.Found'],
                17 => [self::SNIFF_CODE . '.Found'],
                18 => [self::SNIFF_CODE . '.Found'],
                19 => [self::SNIFF_CODE . '.Found'],
                30 => [self::SNIFF_CODE . '.Found'],
                31 => [self::SNIFF_CODE . '.Found'],
                32 => [self::SNIFF_CODE . '.Found'],
                39 => [self::SNIFF_CODE . '.Found'],
                40 => [self::SNIFF_CODE . '.Found'],
                41 => [self::SNIFF_CODE . '.Found'],
                48 => [self::SNIFF_CODE . '.Found'],
                49 => [self::SNIFF_CODE . '.Found'],
                57 => [self::SNIFF_CODE . '.Found'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * Only the single-argument 1:1 swaps (count(), array_sum()) are fixable;
     * the rest need semantic judgement and stay detection-only.
     */
    public function testOnlyUnambiguousSingleArgumentCallsAreFixable(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(17, $file->getErrorCount());
        $this->assertSame(5, $file->getFixableCount());
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
