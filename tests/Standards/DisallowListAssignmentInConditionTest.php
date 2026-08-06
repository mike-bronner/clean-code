<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the CleanCode.Conditionals.DisallowListAssignmentInCondition sniff,
 * which closes the one detection gap between PHPMD's IfStatementAssignment
 * rule (#79) and Generic.CodeAnalysis.AssignmentInCondition — a list()
 * destructuring target in a condition.
 *
 * Fixtures live in Fixtures/DisallowListAssignmentInConditionSniff/ beside
 * this file. There are no autofix-before/after fixtures: the sniff is
 * report-only, matching PHPMD, because turning a destructuring assignment
 * into a comparison is a guess at intent rather than a mechanical rewrite.
 * testViolationsAreReportedWithoutAnAutoFix pins that.
 */
class DisallowListAssignmentInConditionTest extends TestCase
{
    private const SNIFF = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

    private const ERROR_CODE = self::SNIFF . '.Found';

    /**
     * Every condition-bearing construct the sniff covers, in fixture order:
     * if, elseif, keyed list() in an if, a list() nested in a call argument in
     * an if, while, the condition section of a for, that same section reached
     * through a closure, do-while, switch, match, and an if at file scope.
     */
    private const VIOLATION_LINES = [14, 16, 20, 26, 30, 34, 44, 53, 55, 60, 66];

    public function testMasterRulesetMakesTheSniffReachable(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF, $ruleset->sniffCodes);
    }

    public function testPassingFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEveryConditionShapeIsFlaggedAtItsOwnLine(): void
    {
        $file = $this->processFixture('failing.inc');
        $errors = $file->getErrors();

        $this->assertSame(self::VIOLATION_LINES, array_keys($errors));

        foreach (self::VIOLATION_LINES as $line) {
            $lineErrors = array_merge(...array_values($errors[$line]));

            $this->assertCount(1, $lineErrors);
            $this->assertSame(self::ERROR_CODE, $lineErrors[0]['source']);
        }
    }

    public function testViolationsAreReportedAsErrorsRatherThanWarnings(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(count(self::VIOLATION_LINES), $file->getErrorCount());
        $this->assertSame(0, $file->getWarningCount());
        $this->assertSame([], $file->getWarnings());
    }

    public function testViolationsAreReportedWithoutAnAutoFix(): void
    {
        $file = $this->processFixture('failing.inc');

        // Guard against a vacuous pass: an empty report also has zero fixable
        // violations, so pin that the violations are actually there first.
        $this->assertSame(count(self::VIOLATION_LINES), $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());

        foreach ($file->getErrors() as $columns) {
            foreach (array_merge(...array_values($columns)) as $error) {
                $this->assertFalse($error['fixable']);
            }
        }
    }

    /**
     * A for header's three sections are separated by the header's own
     * semicolons. A closure in the initialiser or the increment carries
     * semicolons of its own, and reading one of those as a section separator
     * puts the closure's whole body inside the "condition".
     *
     * passing.inc holds that shape on both sides of the condition and must
     * stay silent; failing.inc holds the same shape in the condition section
     * itself and must still be reported. The pair is what makes this
     * discriminating: a sniff that ignored closures wholesale would pass the
     * first assertion and fail the second.
     */
    public function testAClosureInAForHeaderDoesNotMoveTheConditionSection(): void
    {
        $silent = $this->processFixture('passing.inc');

        $this->assertSame([], $silent->getErrors());

        $reported = $this->processFixture('failing.inc');
        $conditionSectionLine = 44;

        $this->assertArrayHasKey($conditionSectionLine, $reported->getErrors());
    }

    /**
     * A for header with fewer than two section separators has no condition
     * section to sit in. Only malformed source can produce one, and the sniff
     * answers "not a condition" rather than guessing at a span.
     *
     * The fixture reaches the guard three ways — no separator at all, and one
     * separator with the list() on either side of it — so the guard is
     * exercised, not just asserted around.
     *
     * The assertions below pin the outcome: nothing reported, nothing crashed.
     * The guard itself is load-bearing on top of that, and this is the test
     * that proves it: removing it turns this case red, because PHPUnit's error
     * handler surfaces the undefined-key read of the second separator that
     * follows. Both halves were checked by running that mutation.
     */
    public function testForHeaderWithoutTwoSectionSeparatorsIsNotAConditionSection(): void
    {
        $file = $this->processFixture('malformed-for-header.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * An unterminated list() carries a null closer, so the sniff has no
     * position to look past for an "=".
     *
     * This pins the outcome, not the guard: removing the null-closer check
     * leaves this fixture silent too, because the search from the resulting
     * bogus offset lands on a token that is not "=". No fixture can separate
     * the two, so the guard is documented in the sniff as explicitness rather
     * than claimed as tested behaviour. What is proven here is that malformed
     * source produces no report and no crash.
     */
    public function testUnterminatedListIsRefusedRatherThanGuessedAt(): void
    {
        $file = $this->processFixture('unterminated.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, so the CleanCode standard never loads.
        // populateTokenListeners() re-applies the remaining listener map.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(
            __DIR__ . '/Fixtures/DisallowListAssignmentInConditionSniff/' . $fixture,
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
        // so the ruleset can resolve the SlevomatCodingStandard sniffs the
        // master ruleset references alongside this one.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }
}
