<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Constructors;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Constructors: Property Promotion standard (issue #47), enforced
 * by Slevomat's RequireConstructorPropertyPromotion sniff as wired into the
 * master rules.xml.
 *
 * The line maps below refer to RequireConstructorPropertyPromotionTest.inc;
 * the expected auto-fix result lives in the .inc.fixed file beside it.
 */
class RequireConstructorPropertyPromotionTest extends TestCase
{
    private const SNIFF = 'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion';

    private const FIXTURE = __DIR__ . '/RequireConstructorPropertyPromotionTest.inc';

    public function testViolationsAreReportedOnTheExpectedLines(): void
    {
        $file = $this->processFixture(self::FIXTURE);

        $lineCounts = [];

        foreach ($file->getErrors() as $line => $columns) {
            $lineCounts[$line] = array_sum(array_map('count', $columns));
        }

        $this->assertSame(
            [
                17 => 1,
                28 => 1,
                39 => 1,
            ],
            $lineCounts,
        );
    }

    public function testFixerPromotesPropertiesToConstructorParameters(): void
    {
        $file = $this->processFixture(self::FIXTURE);
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(self::FIXTURE . '.fixed', $file->fixer->getContents());
    }

    public function testFixedFixturePassesTheSniffWithZeroViolations(): void
    {
        $file = $this->processFixture(self::FIXTURE . '.fixed');

        $this->assertSame(0, $file->getErrorCount() + $file->getWarningCount());
    }

    private function processFixture(string $path): LocalFile
    {
        $config = new Config();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 3) . '/rules.xml'];
        $config->sniffs = [self::SNIFF];

        $file = new LocalFile($path, new Ruleset($config), $config);
        $file->process();

        return $file;
    }
}
