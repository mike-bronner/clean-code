<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the Squiz.PHP.Eval rule as configured in the master
 * rules.xml, which replaces PHPMD's Design/EvalExpression rule (issue #107).
 *
 * Fixtures live in Fixtures/Eval/ beside this file, following the repo's
 * fixture convention (CONTRIBUTING.md): compliant and violating code in
 * separate files, passing.inc and failing.inc. There are no
 * autofix-before.inc / autofix-after.inc files because the rule is not
 * auto-fixable — Squiz\Sniffs\PHP\EvalSniff reports through addWarning() and
 * registers no fixer, so phpcbf cannot act on it, and PHPMD offers no auto-fix
 * for an eval expression either. testEvalExpressionsAreReportedWithoutAnAutoFix
 * pins that, so the missing autofix fixtures stay an asserted fact rather than
 * an assumption.
 *
 * Two properties of the mapping are pinned here beyond plain detection. The
 * sniff reports a *warning* out of the box; rules.xml raises it to an error so
 * eval() usage fails a phpcs run the way it fails a phpmd run, so the tests
 * assert the reports land in getErrors() and that getWarnings() stays empty.
 * The rule is also report-only, which the fixable-count assertion pins.
 */
class EvalExpressionTest extends TestCase
{
    private const SNIFF_CODE = 'Squiz.PHP.Eval';

    private const VIOLATION_LINES = [12, 20, 25];

    public function testRuleIsRegisteredInMasterRuleset(): void
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

    public function testEachEvalExpressionIsFlaggedAtItsOwnLine(): void
    {
        $file = $this->processFixture('failing.inc');
        $errors = $file->getErrors();

        $this->assertSame(self::VIOLATION_LINES, array_keys($errors));

        foreach (self::VIOLATION_LINES as $line) {
            $lineErrors = array_merge(...array_values($errors[$line]));

            $this->assertCount(1, $lineErrors);
            $this->assertSame(self::SNIFF_CODE . '.Discouraged', $lineErrors[0]['source']);
        }
    }

    public function testEvalExpressionsAreReportedAsErrorsRatherThanWarnings(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(count(self::VIOLATION_LINES), $file->getErrorCount());
        $this->assertSame(0, $file->getWarningCount());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEvalExpressionsAreReportedWithoutAnAutoFix(): void
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

    public function testMethodsNamedEvalAreNotFlagged(): void
    {
        $file = $this->processFixture('boundaries.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <type> configured there.
        // populateTokenListeners() re-applies the remaining listener map.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . '/Fixtures/Eval/' . $fixture, $ruleset, $config);
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
