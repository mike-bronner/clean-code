<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Methods.DeclaredParameters sniff as
 * wired into the master rules.xml (Methods: Declared Parameters, issue #69).
 * Fixtures live in Fixtures/DeclaredParameters/ beside this file.
 *
 * The sniff is detection-only, so there is no auto-fix assertion — instead the
 * tests prove every reported violation is non-fixable.
 */
class DeclaredParametersTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Methods.DeclaredParameters';

    private const VIOLATION = self::SNIFF_CODE . '.DynamicArguments';

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    /**
     * Declared parameters, variadics, magic methods, same-named members, a
     * namespaced lookalike, and a same-named function declaration all pass.
     */
    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEveryDynamicArgumentFunctionIsFlaggedAtItsOwnLineAndColumn(): void
    {
        $file = $this->processFixture('violations.inc');

        $this->assertSame(
            [
                // func_get_args(), func_num_args(), and func_get_arg() each
                // read arguments the signature never declared.
                ['line' => 11, 'column' => 17, 'source' => self::VIOLATION],
                ['line' => 13, 'column' => 13, 'source' => self::VIOLATION],
                ['line' => 14, 'column' => 21, 'source' => self::VIOLATION],
                // `\func_get_args()` qualifies the global namespace — still
                // PHP's own function.
                ['line' => 22, 'column' => 17, 'source' => self::VIOLATION],
                // PHP function names are case-insensitive.
                ['line' => 27, 'column' => 16, 'source' => self::VIOLATION],
            ],
            $this->violations($file)
        );
    }

    /**
     * The magic-method exemption belongs to the magic method's own body: a
     * closure or arrow function inside one declares its own parameter list, and
     * a plain function named like a magic method is not one.
     */
    public function testExemptionAppliesToTheMagicMethodBodyOnly(): void
    {
        $file = $this->processFixture('scopes.inc');

        $this->assertSame(
            [
                // Closure in an ordinary method.
                ['line' => 12, 'column' => 20, 'source' => self::VIOLATION],
                // Closure inside __call().
                ['line' => 21, 'column' => 20, 'source' => self::VIOLATION],
                // Arrow function inside __invoke().
                ['line' => 28, 'column' => 32, 'source' => self::VIOLATION],
                // Plain function named __get().
                ['line' => 44, 'column' => 12, 'source' => self::VIOLATION],
            ],
            $this->violations($file)
        );
    }

    /**
     * The message names the call as written, so a case variant reports itself
     * rather than a normalised name.
     */
    public function testMessageNamesTheOffendingCall(): void
    {
        $file = $this->processFixture('violations.inc');
        $errors = $file->getErrors();

        $this->assertSame(
            'func_num_args() is not allowed; declare the parameter list instead of'
                . ' reading arguments dynamically',
            $errors[13][13][0]['message']
        );
        $this->assertSame(
            'FUNC_GET_ARGS() is not allowed; declare the parameter list instead of'
                . ' reading arguments dynamically',
            $errors[27][16][0]['message']
        );
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        foreach (['violations.inc', 'scopes.inc'] as $fixture) {
            $file = $this->processFixture($fixture);

            foreach ($file->getErrors() as $columns) {
                foreach ($columns as $messages) {
                    foreach ($messages as $message) {
                        $this->assertFalse($message['fixable'], $fixture . ' violations must be detection-only');
                    }
                }
            }
        }
    }

    /**
     * Flattens a processed file's errors into an ordered list of
     * line/column/source tuples for exact assertion.
     *
     * @return array<int, array{line: int, column: int, source: string}>
     */
    private function violations(LocalFile $file): array
    {
        $flat = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $column => $messages) {
                foreach ($messages as $message) {
                    $flat[] = ['line' => $line, 'column' => $column, 'source' => $message['source']];
                }
            }
        }

        usort($flat, static fn (array $a, array $b): int => [$a['line'], $a['column']] <=> [$b['line'], $b['column']]);

        return $flat;
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <properties> configured there.
        // populateTokenListeners() re-applies those properties.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . '/Fixtures/DeclaredParameters/' . $fixture, $ruleset, $config);
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
        // so the ruleset can resolve the SlevomatCodingStandard sniffs.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }
}
