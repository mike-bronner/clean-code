<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Naming;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the naming casing-convention rules (issue #22) wired
 * into the master rules.xml:
 *
 * - Squiz.NamingConventions.ValidVariableName — camelCase variables and
 *   properties (PrivateNoUnderscore excluded: no underscore prefix demanded).
 * - PSR1.Methods.CamelCapsMethodName — camelCase methods (magic exempt).
 * - Squiz.Classes.ValidClassName — PascalCase classes, interfaces, traits,
 *   and enums (abstract classes are covered via their class token).
 *
 * All three sniffs are non-strict about consecutive capitals, so acronym
 * runs pass: $userID, getUserID(), and HTTPClient are all accepted alongside
 * $userId, getUserId(), and HttpClient. Leading underscores on private
 * members and on locals inside class scope are stripped by the Squiz sniff
 * before the camelCaps check, so private $_legacy and $_inClass slip
 * through — a documented limitation, asserted below so a behavior change
 * surfaces here.
 *
 * The line map refers to CasingConventionsRulesetTest.inc. Violations from
 * rules other than the three above are ignored, so unrelated additions to
 * the master ruleset cannot break this test.
 */
class CasingConventionsRulesetTest extends TestCase
{
    private const NAMING_SNIFFS = [
        'Squiz.NamingConventions.ValidVariableName',
        'PSR1.Methods.CamelCapsMethodName',
        'Squiz.Classes.ValidClassName',
    ];

    /** @var array<int, array<int, string>> line => naming violation sources */
    private static array $violations = [];

    public static function setUpBeforeClass(): void
    {
        // ConfigDouble resets PHPCS's static Config state at construction,
        // so settings latched by rulesets built in earlier tests cannot leak
        // in here (or out of here into later tests). The explicit argv also
        // stops Config from parsing PHPUnit's own CLI arguments.
        $config = new ConfigDouble(['--standard=' . dirname(__DIR__, 3) . '/rules.xml']);

        // Pin installed_paths explicitly (after ConfigDouble blanks the
        // static config data): the master ruleset references the Slevomat
        // standard for the Exceptions rules.
        Config::setConfigData(
            'installed_paths',
            dirname(__DIR__, 3) . '/vendor/slevomat/coding-standard',
            true
        );

        $file = new LocalFile(
            __DIR__ . '/CasingConventionsRulesetTest.inc',
            new Ruleset($config),
            $config
        );
        $file->process();

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    if (self::isNamingSource($error['source'])) {
                        self::$violations[$line][] = $error['source'];
                    }
                }
            }
        }

        ksort(self::$violations);
    }

    public function testFlagsExactlyTheExpectedLines(): void
    {
        $variableSniff = 'Squiz.NamingConventions.ValidVariableName';

        self::assertSame(
            [
                8 => [$variableSniff . '.NotCamelCaps'],
                9 => [$variableSniff . '.NotCamelCaps'],
                10 => [$variableSniff . '.NotCamelCaps'],
                11 => [$variableSniff . '.NotCamelCaps'],
                16 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                17 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                18 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                20 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                22 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                24 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
                34 => [$variableSniff . '.MemberNotCamelCaps'],
                35 => [$variableSniff . '.MemberNotCamelCaps'],
                36 => [$variableSniff . '.PublicHasUnderscore'],
                43 => [$variableSniff . '.NotCamelCaps'],
                48 => ['PSR1.Methods.CamelCapsMethodName.NotCamelCaps'],
                49 => ['PSR1.Methods.CamelCapsMethodName.NotCamelCaps'],
            ],
            self::$violations
        );
    }

    public function testPrivatePropertiesNeedNoUnderscorePrefix(): void
    {
        $sources = array_merge(...array_values(self::$violations) ?: [[]]);

        self::assertNotContains(
            'Squiz.NamingConventions.ValidVariableName.PrivateNoUnderscore',
            $sources,
            'PrivateNoUnderscore must stay excluded from the master ruleset.'
        );
    }

    private static function isNamingSource(string $source): bool
    {
        foreach (self::NAMING_SNIFFS as $sniff) {
            if (strpos($source, $sniff . '.') === 0) {
                return true;
            }
        }

        return false;
    }
}
