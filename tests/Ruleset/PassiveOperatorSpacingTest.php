<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the "Operators: Passive" standard (#64) as wired into
 * the master rules.xml. The standard is enforced by four sniffs together — the
 * custom CleanCode.WhiteSpace.PassiveOperatorSpacing (identity, negation, error
 * control, execution) plus three existing sniffs for increment/decrement, the
 * object operator, and array access — so the test isolates and drives that set.
 * Fixtures live in Fixtures/PassiveOperatorSpacing/ beside this file.
 */
class PassiveOperatorSpacingTest extends TestCase
{
    /**
     * The sniff codes that together enforce the passive-operator standard.
     *
     * @var array<int, string>
     */
    private const SNIFF_CODES = [
        'CleanCode.WhiteSpace.PassiveOperatorSpacing',
        'Generic.WhiteSpace.IncrementDecrementSpacing',
        'Squiz.WhiteSpace.ObjectOperatorSpacing',
        'Squiz.Arrays.ArrayBracketSpacing',
    ];

    public function testEveryEnforcingRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        foreach (self::SNIFF_CODES as $code) {
            $this->assertArrayHasKey($code, $ruleset->sniffCodes);
        }
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEachPassiveOperatorViolationIsFlaggedAtItsLine(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                9  => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.Identity'],
                10 => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.Negation'],
                11 => ['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterIncrement'],
                12 => ['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterIncrement'],
                13 => ['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterDecrement'],
                14 => ['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterDecrement'],
                15 => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.ErrorControl'],
                16 => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.Execution'],
                17 => ['Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket'],
                18 => [
                    'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
                    'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
                ],
                19 => [
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                ],
                20 => [
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                ],
            ],
            $this->sourcesByLine($file)
        );
    }

    public function testBinaryPlusAndMinusAreNotFlagged(): void
    {
        $errors = $this->processFixture('violations.inc')->getErrors();

        // Lines 21 (`$a + $a`) and 22 (`$a - $a`) are binary arithmetic, out of
        // scope: only unary identity/negation signs are passive operators.
        $this->assertArrayNotHasKey(21, $errors);
        $this->assertArrayNotHasKey(22, $errors);
    }

    public function testSignActingOnAnotherSignIsLeftUntouched(): void
    {
        // `- -$a` / `+ +$a`: closing the gap would fuse the pair into a
        // decrement/increment and change meaning, so the sniff withholds both
        // the report and the fix rather than corrupt the code.
        $file = $this->processFixture('guarded.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());

        $original = file_get_contents(__DIR__ . '/Fixtures/PassiveOperatorSpacing/guarded.inc');
        $file->fixer->fixFile();

        $this->assertSame($original, $file->fixer->getContents());
    }

    public function testEveryViolationIsAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        foreach ($file->getErrors() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $this->assertTrue($message['fixable'], $message['source'] . ' must be auto-fixable');
                }
            }
        }
    }

    public function testAutoFixProducesTheCompliantFile(): void
    {
        $file = $this->processFixture('violations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/PassiveOperatorSpacing/violations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    /**
     * Collapses a processed file's errors into a `line => sorted source list`
     * map. Sources are sorted so the assertion does not depend on the column
     * order of multiple violations reported on the same line.
     *
     * @return array<int, array<int, string>>
     */
    private function sourcesByLine(LocalFile $file): array
    {
        $byLine = [];

        foreach ($file->getErrors() as $line => $columns) {
            $sources = [];

            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $sources[] = $message['source'];
                }
            }

            sort($sources);
            $byLine[$line] = $sources;
        }

        ksort($byLine);

        return $byLine;
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the four sniffs that enforce this standard so unrelated
        // master-ruleset rules do not colour the assertions. A $config->sniffs
        // restriction cannot be used here: under PHP_CODESNIFFER_IN_TESTS it
        // makes Ruleset skip parsing rules.xml, dropping the <properties>
        // configured there. populateTokenListeners() re-applies them.
        $isolated = [];

        foreach (self::SNIFF_CODES as $code) {
            $sniffClass = $ruleset->sniffCodes[$code];
            $isolated[$sniffClass] = $ruleset->sniffs[$sniffClass];
        }

        $ruleset->sniffs = $isolated;
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . '/Fixtures/PassiveOperatorSpacing/' . $fixture, $ruleset, $config);
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
