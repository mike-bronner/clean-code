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
 * object operator, and array access — so most tests isolate and drive that set
 * to assert this standard's behaviour without unrelated master-ruleset noise
 * colouring the results. testFullRulesetConvergesUnderRealPhpcbf() complements
 * them by driving the *whole* rules.xml through the real phpcbf, catching
 * cross-standard fixer collisions the isolated harness cannot see.
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
                // Interpolated backtick: leading and trailing whitespace inside
                // the backticks are trimmed separately, so both edges report.
                17 => [
                    'CleanCode.WhiteSpace.PassiveOperatorSpacing.Execution',
                    'CleanCode.WhiteSpace.PassiveOperatorSpacing.Execution',
                ],
                18 => ['Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket'],
                19 => [
                    'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
                    'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
                ],
                20 => [
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                ],
                21 => [
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                    'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
                ],
                // Cross-direction signs on an increment/decrement (`- ++$a`,
                // `+ --$a`): closing the gap does not fuse them, so they are
                // flagged and fixed rather than guarded.
                22 => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.Negation'],
                23 => ['CleanCode.WhiteSpace.PassiveOperatorSpacing.Identity'],
            ],
            $this->sourcesByLine($file)
        );
    }

    public function testBinaryPlusAndMinusAreNotFlagged(): void
    {
        $errors = $this->processFixture('violations.inc')->getErrors();

        // Lines 24 (`$a + $a`) and 25 (`$a - $a`) are binary arithmetic, out of
        // scope: only unary identity/negation signs are passive operators.
        // Line 26 (`$a++ + $a`) is the regression guard — the binary `+` after
        // a postfix `++` must not be misread as a unary sign and stripped.
        $this->assertArrayNotHasKey(24, $errors);
        $this->assertArrayNotHasKey(25, $errors);
        $this->assertArrayNotHasKey(26, $errors);
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
     * End-to-end guard the isolated harness above cannot give: it runs only
     * this standard's four sniffs, so a fixer that collides with another
     * master-ruleset rule never surfaces. Here the real phpcbf drives the whole
     * rules.xml over a file that mixes a binary `+`/`-` after a postfix
     * `++`/`--` (which PSR12's OperatorSpacing requires a space around) with
     * spaced passive operators. The unary/binary split must leave the binary
     * operator untouched so the two fixers do not oscillate: phpcbf has to
     * converge (never exit 2) and land exactly on the expected output.
     */
    public function testFullRulesetConvergesUnderRealPhpcbf(): void
    {
        $root = dirname(__DIR__, 2);
        $fixtureDir = __DIR__ . '/Fixtures/PassiveOperatorSpacing';
        $working = tempnam(sys_get_temp_dir(), 'pos');
        $incFile = $working . '.inc';
        copy($fixtureDir . '/convergence.inc', $incFile);

        try {
            $command = escapeshellarg($root . '/vendor/bin/phpcbf')
                . ' --standard=' . escapeshellarg($root . '/rules.xml')
                . ' --extensions=inc --no-cache '
                . escapeshellarg($incFile);
            exec($command . ' 2>&1', $output, $exitCode);

            // phpcbf exit codes: 0 = already clean, 1 = fixed and converged,
            // 2 = FAILED TO FIX (fixers oscillated and could not converge).
            $this->assertNotSame(2, $exitCode, "phpcbf failed to converge:\n" . implode("\n", $output));

            $this->assertStringEqualsFile($fixtureDir . '/convergence.inc.fixed', file_get_contents($incFile));
        } finally {
            @unlink($working);
            @unlink($incFile);
        }
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
