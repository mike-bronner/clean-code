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

    /**
     * An arrow function is an anonymous function and must count as a nesting
     * level like a closure. Nested two deep, the `fn` itself is reported at
     * level 3 (line 16) and its inline `match` body at level 3 (line 17).
     * Deleting T_FN from register() drops the line-16 report — the guard that
     * an over-nested arrow function is no longer a silent false negative.
     */
    public function testArrowFunctionCountsAsNestingLevel(): void
    {
        $errorsByLine = $this->errorsByLine($this->processFixture('arrow-function.inc'));

        $this->assertSame([16, 17], array_keys($errorsByLine));

        foreach ($errorsByLine as $line => $errors) {
            $this->assertCount(1, $errors, "expected exactly one violation on line {$line}");
            $this->assertSame(self::SNIFF_CODE . '.MaxExceeded', $errors[0]['source']);
            $this->assertFalse($errors[0]['fixable']);
        }

        $this->assertSame([16 => 3, 17 => 3], $this->levelsByLine($errorsByLine));
    }

    /**
     * Two-word `else if` is a continuation of the chain, exactly like one-word
     * `elseif`: a 3-level chain is reported once at the leading `if` (line 16),
     * not once per `else if` segment. Deleting the continuation skip makes the
     * chain double-report (lines 18 and 20 as well), breaking this assertion.
     * The branch still adds one level for statements inside it — the `while`
     * nested in an `else if` branch is level 3 (line 36).
     */
    public function testTwoWordElseIfIsReportedOnceLikeElseif(): void
    {
        $errorsByLine = $this->errorsByLine($this->processFixture('else-if-continuation.inc'));

        $this->assertSame([16, 36], array_keys($errorsByLine));

        foreach ($errorsByLine as $line => $errors) {
            $this->assertCount(1, $errors, "expected exactly one violation on line {$line}");
            $this->assertSame(self::SNIFF_CODE . '.MaxExceeded', $errors[0]['source']);
        }

        $this->assertSame([16 => 3, 36 => 3], $this->levelsByLine($errorsByLine));
    }

    /**
     * A control structure nested inside an `elseif` branch (line 18), a plain
     * `else` branch (line 32), a `finally` block (line 48), or a `do-while`
     * loop (line 61) is counted at the correct depth and reported at level 3.
     * Each pins one NESTING_TOKENS entry (T_ELSEIF/T_ELSE/T_FINALLY/T_DO):
     * deleting that entry drops the corresponding report, so the suite catches
     * the regression instead of staying green.
     */
    public function testContinuationBranchAndDoWhileNestingIsCounted(): void
    {
        $errorsByLine = $this->errorsByLine($this->processFixture('continuation-branch-nesting.inc'));

        $this->assertSame([18, 32, 48, 61], array_keys($errorsByLine));

        foreach ($errorsByLine as $line => $errors) {
            $this->assertCount(1, $errors, "expected exactly one violation on line {$line}");
            $this->assertSame(self::SNIFF_CODE . '.MaxExceeded', $errors[0]['source']);
        }

        $this->assertSame(
            [18 => 3, 32 => 3, 48 => 3, 61 => 3],
            $this->levelsByLine($errorsByLine)
        );
    }

    /**
     * AC #3 requires the violation at the correct line *and column*. The excess
     * control structure in boundary-fail.inc (`while` on line 13, indented 16
     * spaces) is reported at column 17 — the token's own column, not the line
     * start.
     */
    public function testViolationIsReportedAtControlStructureColumn(): void
    {
        $file = $this->processFixture('boundary-fail.inc');
        $errors = $file->getErrors();

        $this->assertArrayHasKey(13, $errors);
        $this->assertSame([17], array_keys($errors[13]));
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
