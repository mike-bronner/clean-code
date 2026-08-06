<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the Generic.CodeAnalysis.AssignmentInCondition rule as
 * configured in the master rules.xml, which replaces PHPMD's
 * CleanCode/IfStatementAssignment rule (issue #79). Fixtures live in
 * Fixtures/IfStatementAssignment/ beside this file.
 *
 * The fixtures partition the two tools' behaviour, each partition verified
 * against phpmd 2.15 running rulesets/cleancode.xml/IfStatementAssignment:
 *
 * - compliant.inc — neither tool reports anything.
 * - violations.inc — both tools report the same lines, the same number of
 *   times each. This is the "no gaps" half of the mapping.
 * - broader-than-phpmd.inc — only the Generic sniff reports. PHPMD's rule
 *   reads if/elseif clauses only, accepts a plain "=" only, and visits
 *   function and method bodies only, so compound operators, the other
 *   conditions, and file-scope code fall outside it. Kept rather than
 *   narrowed: it is the same smell, no other PHPMD rule owns those shapes,
 *   and the sniff's two codes group the constructs together so the subset is
 *   not expressible as configuration.
 * - list-gap.inc — only PHPMD reports, because the Generic sniff's
 *   left-hand-side walk abandons a list() destructuring target at its closing
 *   parenthesis. This is the gap the custom
 *   CleanCode.Conditionals.DisallowListAssignmentInCondition sniff closes, so
 *   the assertion here is that the *master ruleset* reports those lines even
 *   though the Generic sniff alone does not.
 *
 * Two properties beyond plain detection are pinned. The sniff reports
 * warnings out of the box; rules.xml raises it to an error so an assignment
 * in a condition fails a phpcs run the way it fails a phpmd run. It also has
 * no fixer, matching PHPMD, which the fixable-count assertion pins.
 */
class IfStatementAssignmentTest extends TestCase
{
    private const SNIFF_CODE = 'Generic.CodeAnalysis.AssignmentInCondition';

    private const LIST_SNIFF_CODE = 'CleanCode.Conditionals.DisallowListAssignmentInCondition';

    /**
     * Line => number of reports, for the shapes PHPMD reports too. Confirmed
     * identical under phpmd 2.15.
     */
    private const SHARED_VIOLATIONS = [
        14 => 1,
        16 => 1,
        20 => 2,
        24 => 1,
        28 => 2,
        32 => 1,
        36 => 1,
        40 => 1,
        44 => 1,
        45 => 1,
    ];

    /**
     * Line => number of reports, for the shapes only the Generic sniff
     * reports. Confirmed silent under phpmd 2.15.
     */
    private const BROADER_VIOLATIONS = [
        20 => 1,
        24 => 1,
        28 => 1,
        32 => 1,
        36 => 1,
        42 => 1,
        44 => 1,
        45 => 1,
        49 => 1,
        57 => 1,
    ];

    /**
     * Line => number of reports, for the list() shapes only PHPMD reports out
     * of the box. Confirmed flagged under phpmd 2.15.
     */
    private const LIST_VIOLATIONS = [
        18 => 1,
        20 => 1,
        24 => 1,
    ];

    public function testRuleIsRegisteredInMasterRuleset(): void
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

    public function testEveryAssignmentPhpmdReportsIsFlaggedAtTheSameLine(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(self::SHARED_VIOLATIONS, $this->errorCountsByLine($file));
        $this->assertSame([self::SNIFF_CODE . '.Found'], $this->errorSources($file));
    }

    public function testConditionsPhpmdOverlooksAreFlaggedToo(): void
    {
        $file = $this->processFixture('broader-than-phpmd.inc');

        $this->assertSame(self::BROADER_VIOLATIONS, $this->errorCountsByLine($file));
        $this->assertSame(
            [self::SNIFF_CODE . '.Found', self::SNIFF_CODE . '.FoundInWhileCondition'],
            $this->errorSources($file)
        );
    }

    /**
     * The Generic sniff alone is silent on a list() destructuring target, so
     * this asserts the gap is real (nothing from that sniff) *and* closed
     * (the custom sniff reports every line PHPMD does). Asserting only the
     * total would pass if the Generic sniff had quietly started covering it.
     */
    public function testListDestructuringGapIsClosedByTheCustomSniff(): void
    {
        $file = $this->processFixture('list-gap.inc');

        $this->assertSame(self::LIST_VIOLATIONS, $this->errorCountsByLine($file));
        $this->assertSame([self::LIST_SNIFF_CODE . '.Found'], $this->errorSources($file));
    }

    public function testAssignmentsInConditionsAreReportedAsErrorsRatherThanWarnings(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(array_sum(self::SHARED_VIOLATIONS), $file->getErrorCount());
        $this->assertSame(0, $file->getWarningCount());
        $this->assertSame([], $file->getWarnings());
    }

    public function testAssignmentsInConditionsAreReportedWithoutAnAutoFix(): void
    {
        $file = $this->processFixture('violations.inc');

        // Guard against a vacuous pass: an empty report also has zero fixable
        // violations, so pin that the violations are actually there first.
        $this->assertSame(array_sum(self::SHARED_VIOLATIONS), $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());

        foreach ($file->getErrors() as $columns) {
            foreach (array_merge(...array_values($columns)) as $error) {
                $this->assertFalse($error['fixable']);
            }
        }
    }

    /**
     * Runs the fixture through the two sniffs that carry this rule, and only
     * those two, so a sibling standard landing in rules.xml cannot shift the
     * line map. Both are needed in every run: the point of the mapping is
     * that together they cover what PHPMD covers.
     */
    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniffs under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <type> configured there.
        // populateTokenListeners() re-applies the remaining listener map.
        $sniffs = [];

        foreach ([self::SNIFF_CODE, self::LIST_SNIFF_CODE] as $code) {
            $sniffClass = $ruleset->sniffCodes[$code];
            $sniffs[$sniffClass] = $ruleset->sniffs[$sniffClass];
        }

        $ruleset->sniffs = $sniffs;
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . '/Fixtures/IfStatementAssignment/' . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    /**
     * @return array<int, int> line number => error count
     */
    private function errorCountsByLine(LocalFile $file): array
    {
        $counts = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                $counts[$line] = ($counts[$line] ?? 0) + count($errors);
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<int, string> the distinct sniff codes reported, sorted
     */
    private function errorSources(LocalFile $file): array
    {
        $sources = [];

        foreach ($file->getErrors() as $columns) {
            foreach (array_merge(...array_values($columns)) as $error) {
                $sources[] = $error['source'];
            }
        }

        $sources = array_values(array_unique($sources));
        sort($sources);

        return $sources;
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
