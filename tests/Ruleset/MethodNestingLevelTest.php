<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Metrics.MethodNestingLevel sniff
 * (Indentation: Methods — max 2 nesting levels, issue #36) as auto-registered
 * through the master rules.xml. Fixtures live in
 * Fixtures/MethodNestingLevelSniff/ beside this file.
 *
 * The rule is not auto-fixable — reducing nesting requires a semantic refactor
 * a token rewriter cannot apply safely — so there are no autofix before/after
 * fixtures; the tests instead assert every reported error is non-fixable.
 */
class MethodNestingLevelTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Metrics.MethodNestingLevel';

    public function testSniffIsRegisteredInMasterRuleset(): void
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

    /**
     * Every control structure past the 2-level limit is flagged at its own
     * line, and the reported level (message data) matches the true nesting
     * depth. Covers the required edge cases: nested closures (37/38),
     * switch/case (51), try/catch (63/66), and match (78).
     */
    public function testEachExcessNestingLevelIsFlaggedAtItsOwnLine(): void
    {
        $errorsByLine = $this->errorsByLine($this->processFixture('violations.inc'));

        $this->assertSame([13, 24, 25, 37, 38, 51, 63, 66, 78], array_keys($errorsByLine));

        foreach ($errorsByLine as $line => $errors) {
            $this->assertCount(1, $errors, "expected exactly one violation on line {$line}");
            $this->assertSame(self::SNIFF_CODE . '.MaxExceeded', $errors[0]['source']);
            $this->assertFalse($errors[0]['fixable'], 'nesting violations are not auto-fixable');
        }

        $this->assertSame(
            [13 => 3, 24 => 3, 25 => 4, 37 => 3, 38 => 4, 51 => 3, 63 => 3, 66 => 4, 78 => 3],
            $this->levelsByLine($errorsByLine)
        );
    }

    /**
     * The standard governs method bodies. The same nesting depth in top-level
     * script code (no enclosing function) must not be flagged.
     */
    public function testTopLevelCodeIsOutOfScope(): void
    {
        $file = $this->processFixture('non-method.inc');

        $this->assertSame([], $file->getErrors());
    }

    public function testBoundaryTwoLevelsPasses(): void
    {
        $file = $this->processFixture('boundary-pass.inc');

        $this->assertSame([], $file->getErrors());
    }

    /**
     * The same method as boundary-pass.inc, restructured to exactly 3 levels,
     * fails at the single excess control structure.
     */
    public function testBoundaryThreeLevelsFails(): void
    {
        $errorsByLine = $this->errorsByLine($this->processFixture('boundary-fail.inc'));

        $this->assertSame([13], array_keys($errorsByLine));
        $this->assertSame(self::SNIFF_CODE . '.MaxExceeded', $errorsByLine[13][0]['source']);
        $this->assertSame([13 => 3], $this->levelsByLine($errorsByLine));
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot be
        // used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the configuration wired there.
        // populateTokenListeners() re-applies it.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(
            __DIR__ . '/Fixtures/MethodNestingLevelSniff/' . $fixture,
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
        // so the master ruleset can resolve the SlevomatCodingStandard sniffs.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>> line => list of errors
     */
    private function errorsByLine(LocalFile $file): array
    {
        $byLine = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    $byLine[$line][] = $error;
                }
            }
        }

        ksort($byLine);

        return $byLine;
    }

    /**
     * Pulls the reported nesting level out of each error message, proving the
     * depth count — not just that something was flagged.
     *
     * @param array<int, array<int, array<string, mixed>>> $errorsByLine
     *
     * @return array<int, int> line => reported nesting level
     */
    private function levelsByLine(array $errorsByLine): array
    {
        $levels = [];

        foreach ($errorsByLine as $line => $errors) {
            if (preg_match('/level \((\d+)\)/', $errors[0]['message'], $matches) === 1) {
                $levels[$line] = (int) $matches[1];
            }
        }

        return $levels;
    }
}
