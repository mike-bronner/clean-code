<?php

/**
 * Integration test for the Slevomat TypeHints rules wired into the master
 * ruleset (rules.xml) for the "Type Hints and Return Types" standard (#45).
 *
 * Fixtures live in Fixtures/TypeHints/: compliant.inc must produce zero
 * TypeHints violations, violations.inc must be flagged at the exact lines
 * below, and violations.inc.fixed is the expected phpcbf output — every
 * violation the sniff can infer a native hint for is resolved, the rest
 * remain flagged.
 *
 * Only SlevomatCodingStandard.TypeHints.* sources are asserted on. The
 * fixtures deliberately pack interface/abstract/class cases into a single
 * namespace-less file, so PSR1's one-class-per-file / namespace rules (and
 * any other standard wired into the shared master ruleset) also fire on
 * them — those are out of scope here and are filtered out, so unrelated
 * additions to rules.xml cannot break this test.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

class TypeHintsRulesetTest extends TestCase
{
    private const PARAMETER_MISSING_ANY =
        'SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint';
    private const PARAMETER_MISSING_NATIVE =
        'SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint';
    private const RETURN_MISSING_ANY =
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint';
    private const RETURN_MISSING_NATIVE =
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint';
    private const PROPERTY_MISSING_ANY =
        'SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint';
    private const PROPERTY_MISSING_NATIVE =
        'SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint';

    private static ?Config $config = null;

    private static ?Ruleset $ruleset = null;

    public function testCompliantFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $this->sourcesByLine($file));
    }

    public function testViolationsAreFlaggedAtTheExactLine(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                5 => [self::PROPERTY_MISSING_ANY],
                10 => [self::PROPERTY_MISSING_NATIVE],
                12 => [self::PARAMETER_MISSING_ANY],
                20 => [self::PARAMETER_MISSING_NATIVE],
                25 => [self::RETURN_MISSING_ANY],
                35 => [self::RETURN_MISSING_NATIVE],
                40 => [self::PARAMETER_MISSING_ANY],
                48 => [self::PARAMETER_MISSING_ANY],
                55 => [self::RETURN_MISSING_ANY],
                63 => [self::PARAMETER_MISSING_NATIVE],
            ],
            $this->sourcesByLine($file)
        );
        $this->assertSame([], $file->getWarnings());
    }

    public function testInferrableViolationsAreMarkedFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [10, 20, 35, 63],
            $this->fixableLines($file),
            'Exactly the annotated (inferrable) violations must be fixable.'
        );
    }

    public function testFixerResolvesEveryInferrableHint(): void
    {
        $file = $this->processFixtureWithTypeHintsFixerOnly('violations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/TypeHints/violations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testOnlyUninferrableViolationsRemainAfterFixing(): void
    {
        $file = $this->processFixture('violations.inc.fixed');

        $this->assertSame(
            [
                5 => [self::PROPERTY_MISSING_ANY],
                12 => [self::PARAMETER_MISSING_ANY],
                25 => [self::RETURN_MISSING_ANY],
                40 => [self::PARAMETER_MISSING_ANY],
                48 => [self::PARAMETER_MISSING_ANY],
                55 => [self::RETURN_MISSING_ANY],
            ],
            $this->sourcesByLine($file)
        );
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
            __DIR__ . '/Fixtures/TypeHints/' . $fixture,
            self::$ruleset,
            self::$config
        );
        $file->process();

        return $file;
    }

    /**
     * Like processFixture, but on a ruleset narrowed to the TypeHints sniffs
     * (keeping their master-ruleset configuration, e.g. the UselessAnnotation
     * excludes). The whole-file fixer output is asserted verbatim, so any other
     * auto-fixing rule wired into the shared master ruleset would otherwise
     * alter it — restricting to the sniffs under test keeps this test immune to
     * unrelated additions, matching the reporting assertions above.
     */
    private function processFixtureWithTypeHintsFixerOnly(string $fixture): LocalFile
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
            if (strpos($sniffClass, 'Sniffs\\TypeHints\\') === false) {
                unset($ruleset->sniffs[$sniffClass]);
            }
        }

        $ruleset->populateTokenListeners();

        $file = new LocalFile(
            __DIR__ . '/Fixtures/TypeHints/' . $fixture,
            $ruleset,
            $config
        );
        $file->process();

        return $file;
    }

    /**
     * @return array<int, array<int, string>> line number => sorted TypeHints violation sources
     */
    private function sourcesByLine(LocalFile $file): array
    {
        $sources = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    if (self::isTypeHintsSource($error['source'])) {
                        $sources[$line][] = $error['source'];
                    }
                }
            }
        }

        foreach ($sources as &$lineSources) {
            sort($lineSources);
        }
        unset($lineSources);

        ksort($sources);

        return $sources;
    }

    /**
     * @return array<int, int> line numbers carrying at least one fixable TypeHints violation
     */
    private function fixableLines(LocalFile $file): array
    {
        $lines = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    if ($error['fixable'] && self::isTypeHintsSource($error['source'])) {
                        $lines[] = $line;
                    }
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    private static function isTypeHintsSource(string $source): bool
    {
        return str_starts_with($source, 'SlevomatCodingStandard.TypeHints.');
    }
}
