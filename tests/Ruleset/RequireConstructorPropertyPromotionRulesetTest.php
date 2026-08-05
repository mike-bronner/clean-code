<?php

/**
 * Integration test for the Constructors: Property Promotion standard (#47),
 * enforced by Slevomat's RequireConstructorPropertyPromotion sniff wired into
 * the master ruleset (rules.xml).
 *
 * Fixtures live in Fixtures/RequireConstructorPropertyPromotion/:
 * compliant.inc must produce zero property-promotion violations, violations.inc
 * must be flagged at the exact property-declaration lines below, and
 * violations.inc.fixed is the expected phpcbf output — every non-promoted
 * property + constructor assignment becomes a promoted constructor parameter,
 * carrying its visibility, readonly, and default onto the parameter.
 *
 * Only the RequireConstructorPropertyPromotion source is asserted on. The
 * fixtures pack several classes into one namespace-less file, so PSR1's
 * one-class-per-file rule (and any other standard wired into the shared master
 * ruleset) also fires on them — those are out of scope here and are filtered
 * out, so unrelated additions to rules.xml cannot break this test. The fixer
 * assertion likewise restricts the ruleset to this sniff (keeping its
 * master-ruleset configuration), so no other auto-fixing rule can alter the
 * fixed output.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

class RequireConstructorPropertyPromotionRulesetTest extends TestCase
{
    private const SNIFF =
        'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion';

    private const SNIFF_CLASS = 'RequireConstructorPropertyPromotionSniff';

    private static ?Config $config = null;

    private static ?Ruleset $ruleset = null;

    public function testCompliantFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $this->promotionCountsByLine($file));
    }

    public function testViolationsAreFlaggedAtTheExactLine(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                7 => 1,
                19 => 1,
                30 => 1,
                42 => 1,
                54 => 1,
            ],
            $this->promotionCountsByLine($file)
        );
    }

    public function testFixerPromotesPropertiesCarryingEveryModifier(): void
    {
        $file = $this->processFixtureWithPromotionFixerOnly('violations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/RequireConstructorPropertyPromotion/violations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testFixedFixturePassesTheSniffWithZeroViolations(): void
    {
        $file = $this->processFixture('violations.inc.fixed');

        $this->assertSame([], $this->promotionCountsByLine($file));
    }

    private function processFixture(string $fixture): LocalFile
    {
        if (self::$ruleset === null) {
            $root = dirname(__DIR__, 2);

            // ConfigDouble isolates PHPCS's static config state per suite
            // (the CleanCode sniff suite blanks it), so the installer-written
            // installed_paths is gone by the time this suite runs — point
            // PHPCS at the Slevomat standard explicitly.
            self::$config = new ConfigDouble(['--standard=' . $root . '/rules.xml']);
            Config::setConfigData(
                'installed_paths',
                $root . '/vendor/slevomat/coding-standard',
                true
            );
            self::$ruleset = new Ruleset(self::$config);
        }

        $file = new LocalFile(
            __DIR__ . '/Fixtures/RequireConstructorPropertyPromotion/' . $fixture,
            self::$ruleset,
            self::$config
        );
        $file->process();

        return $file;
    }

    /**
     * Like processFixture, but on a ruleset narrowed to the promotion sniff
     * (keeping its master-ruleset configuration). The fixed output is asserted
     * verbatim, so any other auto-fixing rule wired into the shared master
     * ruleset would otherwise alter it — restricting to the sniff under test
     * keeps this test immune to unrelated additions.
     */
    private function processFixtureWithPromotionFixerOnly(string $fixture): LocalFile
    {
        $root = dirname(__DIR__, 2);

        $config = new ConfigDouble(['--standard=' . $root . '/rules.xml']);
        Config::setConfigData(
            'installed_paths',
            $root . '/vendor/slevomat/coding-standard',
            true
        );

        $ruleset = new Ruleset($config);

        foreach (array_keys($ruleset->sniffs) as $sniffClass) {
            if (strpos($sniffClass, self::SNIFF_CLASS) === false) {
                unset($ruleset->sniffs[$sniffClass]);
            }
        }

        $ruleset->populateTokenListeners();

        $file = new LocalFile(
            __DIR__ . '/Fixtures/RequireConstructorPropertyPromotion/' . $fixture,
            $ruleset,
            $config
        );
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
