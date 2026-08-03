<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Strings.MultilineStrings sniff as
 * wired into the master rules.xml (Code Style: Multiline Strings (HEREDOC),
 * issue #53). Fixtures live in Fixtures/MultilineStrings/ beside this file.
 *
 * Two shapes are covered: a quoted string literal spanning multiple lines
 * (QuotedString, auto-fixed to HEREDOC/NOWDOC) and a multi-line concatenation
 * of quoted strings (Concatenation, detection-only). The fixer assertions prove
 * the conversion is byte-for-byte value-preserving.
 */
class MultilineStringsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Strings.MultilineStrings';

    private const FIXTURE_DIR = '/Fixtures/MultilineStrings/';

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testMultiLineStringLiteralsAreFlaggedAtTheirOpeningQuote(): void
    {
        $file = $this->processFixture('violations.inc');

        // Double-quoted, interpolated, escaped-quote, single-quoted, and
        // escaped single-quote strings — each reported once, on its first line.
        // The last two exercise the fixer's generic escape passthrough:
        // $escapes (\t, \\, \$ in a double-quoted string → HEREDOC body) and
        // $literal (literal \n, \t in a single-quoted string → NOWDOC body).
        $this->assertSame(
            [
                ['line' => 5, 'column' => 7, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 8, 'column' => 11, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 11, 'column' => 10, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 14, 'column' => 7, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 17, 'column' => 8, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 20, 'column' => 12, 'source' => self::SNIFF_CODE . '.QuotedString'],
                ['line' => 24, 'column' => 12, 'source' => self::SNIFF_CODE . '.QuotedString'],
            ],
            $this->violations($file)
        );
    }

    public function testMultiLineConcatenationIsFlaggedOnceAtItsFirstStringOperand(): void
    {
        $file = $this->processFixture('concatenation.inc');

        // Lines 14 and 16 are the two chains of a single ternary statement:
        // both must report. Keying the dedup on findStartOfStatement() (which
        // does not treat ?/: as boundaries) would drop the second chain.
        $this->assertSame(
            [
                ['line' => 3, 'column' => 8, 'source' => self::SNIFF_CODE . '.Concatenation'],
                ['line' => 7, 'column' => 12, 'source' => self::SNIFF_CODE . '.Concatenation'],
                ['line' => 14, 'column' => 7, 'source' => self::SNIFF_CODE . '.Concatenation'],
                ['line' => 16, 'column' => 7, 'source' => self::SNIFF_CODE . '.Concatenation'],
            ],
            $this->violations($file)
        );
    }

    public function testFixerConvertsMultiLineStringsToDocSyntax(): void
    {
        $file = $this->processFixture('violations.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . self::FIXTURE_DIR . 'violations.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testFixedFixtureProducesNoViolations(): void
    {
        $file = $this->processFixture('violations.inc.fixed');

        $this->assertSame([], $this->violations($file));
    }

    /**
     * The strongest behaviour-preservation guard: executing the before and
     * after fixtures must yield byte-identical variable values. A fixer that
     * mangled escaping, chose NOWDOC where interpolation was needed, or dropped
     * a character would make these diverge.
     */
    public function testFixerPreservesStringValuesExactly(): void
    {
        $this->assertSame(
            $this->evaluateFixture('violations.inc'),
            $this->evaluateFixture('violations.inc.fixed')
        );
    }

    public function testConcatenationViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('concatenation.inc');

        foreach ($file->getErrors() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $this->assertFalse(
                        $message['fixable'],
                        'multi-line concatenation is detection-only, not auto-fixable'
                    );
                }
            }
        }
    }

    /**
     * When a body line would collide with the closing marker, the fix is
     * withheld (buildDocString returns null): the violation is still reported,
     * but as a plain — non-fixable — error, because emitting the HEREDOC would
     * place the marker inside the body and close the doc early. The fixer must
     * leave such a file byte-for-byte unchanged.
     */
    public function testMarkerCollisionStringIsReportedNonFixableAndUntouched(): void
    {
        $file = $this->processFixture('marker-collision.inc');

        $this->assertSame(
            [['line' => 7, 'column' => 8, 'source' => self::SNIFF_CODE . '.QuotedString']],
            $this->violations($file)
        );

        foreach ($file->getErrors() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $this->assertFalse(
                        $message['fixable'],
                        'a body line colliding with the closing marker must not be auto-fixable'
                    );
                }
            }
        }

        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . self::FIXTURE_DIR . 'marker-collision.inc',
            $file->fixer->getContents()
        );
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

        // Isolate the sniff under test. A $config->sniffs restriction cannot be
        // used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, dropping the <properties> configured there.
        // populateTokenListeners() re-applies those properties.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . self::FIXTURE_DIR . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    /**
     * Executes a fixture in an isolated scope and returns its defined
     * variables, so the before/after string values can be compared directly.
     *
     * @return array<string, mixed>
     */
    private function evaluateFixture(string $fixture): array
    {
        $load = static function (string $mikeBronnerFixturePath): array {
            require $mikeBronnerFixturePath;

            $vars = get_defined_vars();
            unset($vars['mikeBronnerFixturePath']);

            return $vars;
        };

        return $load(__DIR__ . self::FIXTURE_DIR . $fixture);
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
