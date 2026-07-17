<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Conditionals;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test for the "Conditionals: Ternary Conditionals" standard as
 * wired through the master rules.xml: Slevomat's RequireTernaryOperator
 * (prefer ternaries over trivial if/else) plus the custom
 * CleanCode.Conditionals.DisallowNestedTernary sniff (no nested ternaries).
 *
 * The line maps below refer to TernaryConditionalsRulesetTest.compliant.inc
 * and TernaryConditionalsRulesetTest.violations.inc.
 */
class TernaryConditionalsRulesetTest extends TestCase
{
    private const NESTED_TERNARY = 'CleanCode.Conditionals.DisallowNestedTernary.NestedTernary';

    private const TERNARY_NOT_USED =
        'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed';

    public function testCompliantFixtureProducesNoViolations(): void
    {
        $file = $this->process(__DIR__ . '/TernaryConditionalsRulesetTest.compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testViolationsFixtureFlagsEachCaseAtTheExpectedLine(): void
    {
        $file = $this->process(__DIR__ . '/TernaryConditionalsRulesetTest.violations.inc');

        $expected = [
            4 => [1 => [['source' => self::TERNARY_NOT_USED, 'fixable' => true]]],
            13 => [5 => [['source' => self::TERNARY_NOT_USED, 'fixable' => true]]],
            21 => [34 => [['source' => self::NESTED_TERNARY, 'fixable' => false]]],
            24 => [32 => [['source' => self::NESTED_TERNARY, 'fixable' => false]]],
            28 => [34 => [['source' => self::NESTED_TERNARY, 'fixable' => false]]],
            31 => [33 => [['source' => self::NESTED_TERNARY, 'fixable' => false]]],
            35 => [1 => [['source' => self::TERNARY_NOT_USED, 'fixable' => false]]],
            44 => [1 => [['source' => self::TERNARY_NOT_USED, 'fixable' => false]]],
        ];

        $this->assertSame($expected, $this->errorsByLineAndColumn($file));
        $this->assertSame([], $file->getWarnings());
    }

    private function process(string $fixture): LocalFile
    {
        $config = new ConfigDouble();
        // ConfigDouble wipes the CodeSniffer.conf data the composer installer
        // wrote, so point installed_paths back at Slevomat for this run.
        Config::setConfigData(
            'installed_paths',
            dirname(__DIR__, 3) . ',' . dirname(__DIR__, 3) . '/vendor/slevomat/coding-standard',
            true
        );
        $config->standards = [dirname(__DIR__, 3) . '/rules.xml'];
        $config->sniffs = [
            'CleanCode.Conditionals.DisallowNestedTernary',
            'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator',
        ];
        $config->cache = false;

        $file = new LocalFile($fixture, new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    /**
     * @return array<int, array<int, array<array{source: string, fixable: bool}>>>
     */
    private function errorsByLineAndColumn(LocalFile $file): array
    {
        $errorsByLine = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $column => $violations) {
                foreach ($violations as $violation) {
                    $errorsByLine[$line][$column][] = [
                        'source' => $violation['source'],
                        'fixable' => $violation['fixable'],
                    ];
                }
            }
        }

        ksort($errorsByLine);

        foreach ($errorsByLine as &$columns) {
            ksort($columns);
        }

        return $errorsByLine;
    }
}
