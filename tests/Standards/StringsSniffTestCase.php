<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Shared harness for the custom Strings-standard sniff tests (#25).
 *
 * Per the issue's test-fixture convention, these sniffs are NOT tested through
 * PHPCS's AbstractSniffUnitTest (which hardcodes a single `.inc`/`.inc.fixed`
 * layout). Each sniff drives the real PHPCS engine against separate fixtures:
 * `compliant.inc` (no violations), `violations.inc` (line-map assertions), and
 * `autofix-before.inc` / `autofix-after.inc` (fixer input and expected output),
 * all under `Fixtures/<SniffClassName>/`.
 *
 * Fixtures are processed through the master `rules.xml` narrowed to the single
 * sniff under test, so a sibling Strings sniff (which may legitimately also
 * fire on the same markup) can never perturb another sniff's assertions.
 *
 * The class name intentionally ends in `TestCase` (not `Test`) so PHPUnit's
 * default suffix does not pick it up as a suite of its own.
 */
abstract class StringsSniffTestCase extends TestCase
{
    /**
     * The PHPCS code of the sniff under test, e.g.
     * `CleanCode.Strings.RequireStringInterpolation`.
     */
    abstract protected function sniffCode(): string;

    /**
     * The `Fixtures/<SniffClassName>` folder holding this sniff's fixtures.
     */
    abstract protected function fixtureDirectory(): string;

    public function testSniffIsReachableThroughMasterRuleset(): void
    {
        $this->pinInstalledPaths();

        $config = new Config(['--standard=' . $this->packageRoot() . '/rules.xml']);
        $config->cache = false;

        $ruleset = new Ruleset($config);

        self::assertArrayHasKey($this->sniffCode(), $ruleset->sniffCodes);
    }

    public function testCompliantFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        self::assertSame([], $this->errorCountsByLine($file));
    }

    protected function processFixture(string $fixture): LocalFile
    {
        $this->pinInstalledPaths();

        $config = new Config();
        $config->cache = false;
        $config->standards = [$this->packageRoot() . '/rules.xml'];
        $config->sniffs = [$this->sniffCode()];

        $file = new LocalFile(
            $this->fixturePath($fixture),
            new Ruleset($config),
            $config
        );
        $file->process();

        return $file;
    }

    protected function fixturePath(string $fixture): string
    {
        return __DIR__ . '/Fixtures/' . $this->fixtureDirectory() . '/' . $fixture;
    }

    /**
     * Runs phpcbf over a fixture and returns the fixed source.
     */
    protected function fixedContent(string $fixture): string
    {
        $file = $this->processFixture($fixture);
        $file->fixer->fixFile();

        return $file->fixer->getContents();
    }

    /**
     * @return array<int, int> line number => error count
     */
    protected function errorCountsByLine(LocalFile $file): array
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
     * The 1-based column each violation is reported at, keyed by line — so a
     * test can assert not just the line but the exact column the AC requires.
     * A line with several violations lists each column in ascending order.
     *
     * @return array<int, array<int, int>> line number => list of columns
     */
    protected function errorColumnsByLine(LocalFile $file): array
    {
        $columns = [];

        foreach ($file->getErrors() as $line => $errorsByColumn) {
            foreach ($errorsByColumn as $column => $errors) {
                foreach ($errors as $_) {
                    $columns[$line][] = $column;
                }
            }

            sort($columns[$line]);
        }

        ksort($columns);

        return $columns;
    }

    protected function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Pin both the package root (which contains the CleanCode standard) and
     * Slevomat onto installed_paths. Sibling suites blank PHPCS's static
     * config, so this restores what the composer installer wrote. Under
     * PHP_CODESNIFFER_IN_TESTS a narrowed `$config->sniffs` is resolved by
     * expanding each code as a ruleset reference against installed_paths, so
     * the CleanCode standard must be discoverable there — not only pulled in
     * by rules.xml's relative ref — or the restriction expands to nothing.
     */
    private function pinInstalledPaths(): void
    {
        $root = $this->packageRoot();

        Config::setConfigData(
            'installed_paths',
            $root . ',' . $root . '/vendor/slevomat/coding-standard',
            true
        );
    }
}
