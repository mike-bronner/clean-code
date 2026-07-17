<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Methods: Type Hints (#70) — verifies the Slevomat ParameterTypeHint and
 * ReturnTypeHint rules as configured in the master rules.xml.
 *
 * Third-party rules referenced from rules.xml cannot use PHPCS's
 * AbstractSniffUnitTest harness (it only resolves sniffs belonging to the
 * standard under test), so this test runs the master ruleset programmatically
 * against a line-mapped fixture. The whole ruleset runs unrestricted — a
 * Config::$sniffs restriction would drop the ruleset's message-level
 * exclusions and test a config that never ships. The line maps below refer
 * to Fixtures/MethodTypeHints.inc.
 */
class MethodTypeHintsTest extends TestCase
{
    /**
     * @return array<int, int> line number => expected error count
     */
    private function getErrorList(): array
    {
        return [
            95 => 1,
            103 => 1,
            112 => 1,
            120 => 1,
            125 => 1,
            130 => 2,
            139 => 1,
            148 => 1,
            159 => 1,
            175 => 1,
            183 => 1,
            191 => 1,
            199 => 1,
            207 => 1,
        ];
    }

    public function testFixtureViolationsAreReportedAtTheExpectedLines(): void
    {
        $file = $this->processFixture();

        $actual = [];

        foreach ($file->getErrors() as $line => $columns) {
            $actual[$line] = array_sum(array_map('count', $columns));
        }

        ksort($actual);

        $this->assertSame($this->getErrorList(), $actual);
        $this->assertSame(0, $file->getWarningCount());
    }

    public function testFixableViolationsProduceTheExpectedFixedFile(): void
    {
        $file = $this->processFixture();

        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/MethodTypeHints.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(): LocalFile
    {
        $config = new ConfigDouble();
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble isolates the test from CodeSniffer.conf, which is
        // where the composer installer registers third-party standards, so
        // the Slevomat standard has to be registered in-memory here.
        Config::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        $file = new LocalFile(__DIR__ . '/Fixtures/MethodTypeHints.inc', new Ruleset($config), $config);
        $file->process();

        return $file;
    }
}
