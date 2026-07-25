<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Arrays.ArrayAccessors sniff (Arrays: Array
 * Accessors, #33). Fixtures live in Fixtures/ArrayAccessorsSniff/ beside this
 * file: compliant data_get() usage in passing.inc, the constructs the standard
 * deliberately leaves alone in boundaries.inc, and the flagged reads in
 * failing.inc. The rule is detection-only, so there are no autofix fixtures.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class ArrayAccessorsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Arrays.ArrayAccessors';

    private const FIXTURE_DIR = '/Fixtures/ArrayAccessorsSniff/';

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Write-side access, existence checks, array literals, $this-rooted reads,
     * and method calls are out of the standard's scope and must stay silent —
     * a false positive on any of them makes the rule unusable.
     */
    public function testBoundaryConstructsAreNotFlagged(): void
    {
        $file = $this->processFixture('boundaries.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                9 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                10 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                11 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                18 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
                19 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
                20 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
                27 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                31 => [
                    self::SNIFF_CODE . '.DirectPropertyAccess',
                    self::SNIFF_CODE . '.DirectArrayAccess',
                ],
                36 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                43 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
                44 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                45 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * A chain reads as one `data_get()` call, so it earns one diagnostic:
     * `$payload['address']['city']` (line 10), `$order->address->city`
     * (line 19), the mixed `$payload['items'][0]->name` (line 36), and the
     * variable property `$order->$field['locale']` (line 43) are each reported
     * once, at the variable the chain is rooted in — line 43 in particular
     * proves `$field` is treated as a link in the chain, not a second root.
     */
    public function testAnAccessorChainIsReportedOnceAtItsRoot(): void
    {
        $file = $this->processFixture('failing.inc');
        $errors = $file->getErrors();

        $this->assertCount(1, $errors[10]);
        $this->assertCount(1, $errors[19]);
        $this->assertCount(1, $errors[36]);
        $this->assertCount(1, $errors[43]);
        $this->assertSame(17, array_key_first($errors[36]));
        $this->assertSame(23, array_key_first($errors[43]));
    }

    /**
     * A static property roots its own chain: nothing precedes `$registry` that
     * could carry the diagnostic instead, so `self::$registry['key']` is
     * reported at the property (line 44, column 29) rather than skipped.
     */
    public function testAStaticPropertyChainIsReportedAtTheProperty(): void
    {
        $file = $this->processFixture('failing.inc');
        $errors = $file->getErrors();

        $this->assertCount(1, $errors[44]);
        $this->assertSame(29, array_key_first($errors[44]));
    }

    /**
     * PHP_CodeSniffer tokenizes files mid-edit, where an accessor's bracket or
     * dynamic-member brace may never close. The sniff must survive the missing
     * `bracket_closer` and still report the read rather than let it through.
     */
    public function testAnUnterminatedChainIsReportedWithoutFallingOver(): void
    {
        $file = $this->processFixture('unterminated.inc');

        $this->assertSame(
            [
                9 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                10 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * A dynamic member name hides whether the access is a property or a method
     * call until after the closing brace: `$order->{$field}` (line 45) is a
     * read and is flagged, while `$order->{$name}()` in boundaries.inc is a
     * call and is not.
     */
    public function testADynamicMemberIsFlaggedOnlyWhenItIsNotACall(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $this->assertCount(1, $errors[45]);
        $this->assertSame(20, array_key_first($errors[45]));
    }

    /**
     * Pins the detection-only decision: rewriting a chain to a dotted
     * `data_get()` path is a judgement call, so no violation is auto-fixable.
     */
    public function testViolationsAreDetectionOnly(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(13, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it.
        // A $config->sniffs restriction cannot be used: under
        // PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip parsing rules.xml,
        // which is what pulls the custom CleanCode sniffs in.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . self::FIXTURE_DIR . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    private function createConfig(): ConfigDouble
    {
        $config = new ConfigDouble();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble blanks CodeSniffer.conf, where Composer registers
        // Slevomat's installed path; the master ruleset references Slevomat,
        // so restore it (in memory only) for the rules.xml parse.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }

    /**
     * Collapses PHPCS's line => column => violations structure to a map of
     * line number => list of violation source codes.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, string>>
     */
    private function sourcesByLine(array $messages): array
    {
        $sources = [];

        foreach ($messages as $line => $columns) {
            foreach ($columns as $violations) {
                foreach ($violations as $violation) {
                    $sources[$line][] = $violation['source'];
                }
            }
        }

        ksort($sources);

        return $sources;
    }
}
