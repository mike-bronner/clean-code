<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Constructors;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the Constructors: Property Promotion standard (#47),
 * enforced by Slevomat's RequireConstructorPropertyPromotion sniff wired into
 * the master rules.xml.
 *
 * Isolation follows the repo convention (see CasingConventionsRulesetTest and
 * TypeHintsRulesetTest): ConfigDouble resets PHPCS's static config state —
 * which the sibling *UnitTest suites blank — and installed_paths is pinned so
 * the Slevomat standard resolves. Reporting assertions filter to this sniff's
 * own source, and the fixer assertion restricts the ruleset to this sniff
 * (keeping the ruleset's own configuration), so other auto-fixing rules wired
 * into the shared master ruleset cannot break this test.
 *
 * The line map and fixed output refer to
 * RequireConstructorPropertyPromotionTest.inc / .inc.fixed.
 */
class RequireConstructorPropertyPromotionTest extends TestCase
{
    private const SNIFF = 'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion';

    private const SNIFF_CLASS = 'RequireConstructorPropertyPromotionSniff';

    private const FIXTURE = __DIR__ . '/RequireConstructorPropertyPromotionTest.inc';

    public function testViolationsAreReportedOnTheExpectedLines(): void
    {
        $file = $this->processFixture(self::FIXTURE, false);

        $this->assertSame(
            [
                17 => 1,
                28 => 1,
                39 => 1,
            ],
            $this->promotionCountsByLine($file),
        );
    }

    public function testFixerPromotesPropertiesToConstructorParameters(): void
    {
        $file = $this->processFixture(self::FIXTURE, true);
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(self::FIXTURE . '.fixed', $file->fixer->getContents());
    }

    public function testFixedFixturePassesTheSniffWithZeroViolations(): void
    {
        $file = $this->processFixture(self::FIXTURE . '.fixed', false);

        $this->assertSame([], $this->promotionCountsByLine($file));
    }

    /**
     * Build the master ruleset with PHPCS's static config state reset. When
     * $restrictToSniff is true the ruleset is narrowed to this sniff alone —
     * keeping its master-ruleset configuration — so other auto-fixing rules
     * cannot alter the fixed output.
     */
    private function processFixture(string $path, bool $restrictToSniff): LocalFile
    {
        $root = dirname(__DIR__, 3);

        $config = new ConfigDouble(['--standard=' . $root . '/rules.xml']);
        Config::setConfigData(
            'installed_paths',
            $root . '/vendor/slevomat/coding-standard',
            true,
        );

        $ruleset = new Ruleset($config);

        if ($restrictToSniff) {
            foreach (array_keys($ruleset->sniffs) as $sniffClass) {
                if (strpos($sniffClass, self::SNIFF_CLASS) === false) {
                    unset($ruleset->sniffs[$sniffClass]);
                }
            }

            $ruleset->populateTokenListeners();
        }

        $file = new LocalFile($path, $ruleset, $config);
        $file->process();

        return $file;
    }

    /**
     * @return array<int, int> line number => count of property-promotion violations
     */
    private function promotionCountsByLine(LocalFile $file): array
    {
        $counts = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    if (strpos($error['source'], self::SNIFF . '.') === 0) {
                        $counts[$line] = ($counts[$line] ?? 0) + 1;
                    }
                }
            }
        }

        ksort($counts);

        return $counts;
    }
}
