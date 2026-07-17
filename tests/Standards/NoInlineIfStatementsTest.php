<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Tests the "Conditionals: No Inline If-Statements" standard (#9), enforced by
 * Generic.ControlStructures.InlineControlStructure.
 *
 * PSR12 (referenced in rules.xml) already bundles this sniff, so it is active
 * in the master ruleset regardless; rules.xml also references it explicitly as
 * belt-and-suspenders (CONTRIBUTING step 3 — third-party rules a standard
 * depends on are wired in by name). testMasterRulesetMakesTheSniffReachable
 * therefore proves the sniff is reachable through rules.xml (via either route),
 * not that the explicit ref alone is load-bearing. The behaviour tests pin the
 * sniff in isolation against the fixture, so they stay stable as sibling
 * standards land in the shared ruleset.
 *
 * The line map refers to Fixtures/NoInlineIfStatements.inc; the expected
 * auto-fixed source lives in Fixtures/NoInlineIfStatements.inc.fixed.
 */
class NoInlineIfStatementsTest extends TestCase
{
    private const SNIFF = 'Generic.ControlStructures.InlineControlStructure';

    public function testMasterRulesetMakesTheSniffReachable(): void
    {
        $ruleset = new Ruleset($this->masterRulesetConfig());

        $this->assertArrayHasKey(self::SNIFF, $ruleset->sniffCodes);
    }

    public function testFlagsInlineConditionalsAtTheExpectedLines(): void
    {
        $file = $this->processFixture();

        $expected = [
            25 => 1,
            28 => 1,
            29 => 1,
            32 => 1,
            33 => 1,
            34 => 1,
            37 => 2,
            41 => 1,
            46 => 1,
        ];

        self::assertSame($expected, $this->errorCountsByLine($file));
    }

    public function testEveryViolationIsAutoFixable(): void
    {
        $file = $this->processFixture();

        self::assertGreaterThan(0, $file->getErrorCount());
        self::assertSame($file->getErrorCount(), $file->getFixableCount());
    }

    public function testAddsBracesWhenFixed(): void
    {
        $file = $this->processFixture();
        $file->fixer->fixFile();

        self::assertStringEqualsFile(
            __DIR__ . '/Fixtures/NoInlineIfStatements.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(): LocalFile
    {
        $config = new Config();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];
        $config->sniffs = [self::SNIFF];

        $file = new LocalFile(
            __DIR__ . '/Fixtures/NoInlineIfStatements.inc',
            new Ruleset($config),
            $config
        );
        $file->process();

        return $file;
    }

    private function masterRulesetConfig(): Config
    {
        // Pin installed_paths explicitly so the Slevomat rules the master
        // ruleset references resolve: sibling suites blank PHPCS's static
        // Config data (via ConfigDouble), which would otherwise deregister
        // the Slevomat standard by the time this suite runs.
        Config::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        // Pass an explicit --standard: with an empty argv, Config parses the
        // live $_SERVER['argv'] as PHPCS flags and leaks PHPUnit's own
        // arguments (e.g. --filter) into the shared Config. Unrestricted (no
        // $config->sniffs) so the ruleset is parsed from rules.xml rather than
        // short-circuited to a bundled standard — that is what makes this an
        // honest reachability check.
        $config = new Config(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);
        $config->cache = false;

        return $config;
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
}
