<?php

/**
 * Integration test for the PSR1/2/12 industry standards wired into rules.xml.
 *
 * Runs the master ruleset against the fixtures in fixtures/ via PHPCS's own
 * API and asserts the exact violations (line => count), mirroring the
 * AbstractSniffUnitTest contract. Fixtures with a `.fixed` counterpart are
 * additionally run through the fixer and compared, verifying phpcbf support.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Integration;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

class IndustryStandardsTest extends TestCase
{
    private static Ruleset $ruleset;

    private static Config $config;

    public static function setUpBeforeClass(): void
    {
        // ConfigDouble resets PHPCS's static Config state at construction.
        // Building any earlier Ruleset in the same process latches settings
        // like PSR12's tab-width into the static overridden-defaults list,
        // which a plain Config would then silently skip — changing how tab
        // indentation is tokenized and flagged here.
        self::$config = new ConfigDouble(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);

        // Pin installed_paths explicitly (after ConfigDouble blanks the
        // static config data): the master ruleset references the Slevomat
        // standard for the Exceptions rules and the VariableAnalysis standard
        // for undefined variables.
        Config::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        self::$ruleset = new Ruleset(self::$config);
    }

    /**
     * @return array<string, array{string, array<int, int>, array<int, int>}>
     *         fixture file => [file, error line map, warning line map]
     */
    public static function fixtureProvider(): array
    {
        return [
            'compliant class produces zero violations' => ['compliant.inc', [], []],
            'compliant abstract class produces zero violations' => ['compliant-abstract.inc', [], []],
            'side effects mixed with declarations' => ['side-effects.inc', [], [1 => 1]],
            'inline HTML mixed with a class declaration' => ['mixed-html.inc', [2 => 1, 4 => 1], [1 => 1]],
            'more than one class per file' => ['multiple-classes.inc', [9 => 1], []],
            'class outside a namespace' => ['no-namespace.inc', [3 => 1], []],
            // 9 => 3 / 11 => 2 fold in the TypeHints property/return-hint errors
            // the master ruleset now also flags (untyped `var $legacy` and the
            // `run()` return) alongside the PSR12 missing-visibility errors.
            'missing member visibility' => ['visibility.inc', [9 => 3, 11 => 2], [7 => 1]],
            // The master ruleset's Line Length rule (#3) overrides PSR-12's
            // soft limit: with absoluteLineLimit=120 a line past 120 chars is
            // an error, not a warning. Fixture line 7 is 124 chars.
            'line exceeding the 120-character hard limit' => ['line-length.inc', [7 => 1], []],
            'incorrect and tab indentation' => ['indentation.inc', [9 => 1, 10 => 1], []],
            'braces not on their required lines' => ['braces.inc', [5 => 1, 6 => 1], []],
            'malformed control structures' => ['control-structures.inc', [9 => 2, 11 => 1], []],
        ];
    }

    /**
     * @dataProvider fixtureProvider
     *
     * @param array<int, int> $expectedErrors
     * @param array<int, int> $expectedWarnings
     */
    public function testFixtureReportsExpectedViolations(
        string $fixture,
        array $expectedErrors,
        array $expectedWarnings
    ): void {
        $file = $this->processFixture($fixture);

        $this->assertSame($expectedErrors, $this->violationLineMap($file->getErrors()), 'Errors in ' . $fixture);
        $this->assertSame(
            $expectedWarnings,
            $this->violationLineMap($file->getWarnings()),
            'Warnings in ' . $fixture
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixableFixtureProvider(): array
    {
        return [
            'indentation is auto-fixable' => ['indentation.inc'],
            'brace placement is auto-fixable' => ['braces.inc'],
            'control structures are auto-fixable' => ['control-structures.inc'],
        ];
    }

    /**
     * @dataProvider fixableFixtureProvider
     */
    public function testFixerProducesExpectedOutput(string $fixture): void
    {
        $file = $this->processFixture($fixture);
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            self::fixturePath($fixture . '.fixed'),
            $file->fixer->getContents(),
            'Fixer output for ' . $fixture
        );
    }

    /**
     * @return array<string, array{string, array<int, array<int, string>>}>
     *         fixture => [path, line => violation sources]
     */
    public static function customStandardFixtureProvider(): array
    {
        $root = dirname(__DIR__, 2);
        $directRead = 'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess';
        $undefined = 'VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable';

        return [
            // The chains this fixture exists to lay out are, by definition,
            // direct property reads, so the array-accessors standard (#33)
            // flags every one of them. That is the two standards agreeing, not
            // a PSR12 conflict: CleanCode.ClearCode.OneThoughtPerLine governs
            // how a chain is broken across lines, CleanCode.Arrays.ArrayAccessors
            // says the chain should be a data_get() call in the first place.
            //
            // The chain receivers ($user, $items, $order, $builder, ...) are
            // never assigned in this fixture, so the undefined-variable
            // standard (#85) flags each read too — the same kind of pinned
            // fixture noise as the side-effect violations below, not a
            // conflict. Seeding them here would shift every line number the
            // OneThoughtPerLine unit test pins against the same file.
            // Pinned per line so any *other* new violation still fails here.
            'one-thought-per-line chain style is PSR12-clean' => [
                $root . '/CleanCode/Tests/ClearCode/OneThoughtPerLineUnitTest.inc.fixed',
                [
                    3 => [$directRead, $undefined],
                    4 => [$directRead, $undefined],
                    9 => [$directRead, $undefined, $undefined],
                    10 => [$undefined],
                    11 => [$directRead, $directRead],
                    12 => [$directRead, $directRead, $undefined, $undefined],
                    20 => [$undefined],
                    23 => [$undefined],
                    25 => [$directRead, $undefined],
                    30 => [$directRead, $undefined],
                    38 => [$undefined],
                    41 => [$directRead, $undefined],
                    45 => [$undefined, $undefined],
                    47 => [$undefined],
                    49 => [$undefined, $undefined],
                    52 => [$undefined],
                ],
            ],
            'throwable-only catches are PSR12-clean' => [
                $root . '/tests/Rules/Fixtures/ReferenceThrowableOnly.inc.fixed',
                [
                    1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
                    79 => ['PSR1.Classes.ClassDeclaration.MissingNamespace'],
                ],
            ],
            'non-capturing catches are PSR12-clean' => [
                $root . '/tests/Rules/Fixtures/RequireNonCapturingCatch.inc.fixed',
                [
                    1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
                ],
            ],
        ];
    }

    /**
     * The clean-code standards take precedence over the industry baseline:
     * code shaped by the custom rules' own fixers must not be flagged by
     * the PSR12 reference in the master ruleset. The only accepted
     * violations are structural fixture noise (procedural code sharing a
     * file with a class), pinned exactly — so any new PSR12-vs-custom
     * conflict fails here and gets carved out of the PSR12 reference in
     * rules.xml via <exclude>.
     *
     * @dataProvider customStandardFixtureProvider
     *
     * @param array<int, array<int, string>> $expected line => violation sources
     */
    public function testCustomStandardShapedCodeStaysPsr12Clean(string $path, array $expected): void
    {
        $file = new LocalFile($path, self::$ruleset, self::$config);
        $file->process();

        $this->assertSame($expected, $this->violationSourceMap($file), 'Violations in ' . basename($path));
    }

    /**
     * Flatten errors and warnings to line => sorted violation source codes.
     *
     * @return array<int, array<int, string>>
     */
    private function violationSourceMap(LocalFile $file): array
    {
        $map = [];

        foreach ([$file->getErrors(), $file->getWarnings()] as $violations) {
            foreach ($violations as $line => $columns) {
                foreach ($columns as $errors) {
                    foreach ($errors as $error) {
                        $map[$line][] = $error['source'];
                    }
                }
            }
        }

        ksort($map);
        array_walk($map, static function (array &$sources): void {
            sort($sources);
        });

        return $map;
    }

    private function processFixture(string $fixture): LocalFile
    {
        $file = new LocalFile(self::fixturePath($fixture), self::$ruleset, self::$config);
        $file->process();

        return $file;
    }

    private static function fixturePath(string $fixture): string
    {
        return __DIR__ . '/fixtures/' . $fixture;
    }

    /**
     * Flatten PHPCS's line => column => violations structure to line => count.
     *
     * @param array<int, array<int, array<int, mixed>>> $violations
     *
     * @return array<int, int>
     */
    private function violationLineMap(array $violations): array
    {
        $map = [];

        foreach ($violations as $line => $columns) {
            $map[$line] = array_sum(array_map('count', $columns));
        }

        ksort($map);

        return $map;
    }
}
