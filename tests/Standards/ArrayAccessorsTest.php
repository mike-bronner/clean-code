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
     *
     * Write-side means every shape the target can take, not just the operator
     * cases: the fixture also covers destructuring (`[$payload['first']] =
     * $source`, `list(...)`, keyed and nested patterns), a reference bind
     * (`$reference = &$payload['name']`), and `foreach` value, key, and
     * pattern targets. A `data_get()` rewrite of any of them either loses the
     * assignment or drops the reference, so flagging one leaves the developer
     * with no compliant form of the statement.
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
                52 => [
                    self::SNIFF_CODE . '.DirectArrayAccess',
                    self::SNIFF_CODE . '.DirectArrayAccess',
                ],
                53 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                54 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                56 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                60 => [self::SNIFF_CODE . '.DirectArrayAccess'],
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
     * A read stays a read when it sits next to a write, which is where the
     * read/write boundary is easiest to get wrong. Each line pins one side of
     * a distinction the sniff has to draw from the token stream:
     *
     * - line 52, `[$payload['id'] => $payload['name']]` — an accessor before
     *   `=>` is the key of an array literal, read to build it. `=>` is a
     *   member of PHPCS's assignment-token set, so treating that set as
     *   "writes" silently drops the key side; both sides must report.
     * - line 53, `$target[$payload['index']] = 'set'` — the read is an index
     *   *into* a write target. The `]` enclosing it closes an index, not a
     *   destructuring pattern, so the trailing `=` does not make it a write.
     * - line 54, `$mask & $payload['flags']` — the `&` is a bitwise and, not
     *   the reference bind that boundaries.inc exempts.
     * - line 56, `foreach ($payload['rows'] as $row)` — the subject of a
     *   `foreach` is read; only the `as` clause is assigned into.
     * - line 60, `strtoupper($payload['label'])` — an ordinary call's
     *   parentheses have no owning construct at all, unlike the `foreach` and
     *   `if` parentheses elsewhere in this fixture.
     */
    public function testReadsBesideWritesAreStillFlagged(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $this->assertSame([19, 37], array_keys($errors[52]));
        $this->assertSame(17, array_key_first($errors[53]));
        $this->assertSame(26, array_key_first($errors[54]));
        $this->assertSame(18, array_key_first($errors[56]));
        $this->assertSame(29, array_key_first($errors[60]));
        $this->assertCount(1, $errors[53]);
        $this->assertCount(1, $errors[54]);
        $this->assertCount(1, $errors[56]);
        $this->assertCount(1, $errors[60]);
    }

    /**
     * Pins the detection-only decision: rewriting a chain to a dotted
     * `data_get()` path is a judgement call, so no violation is auto-fixable.
     */
    public function testViolationsAreDetectionOnly(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(19, $file->getErrorCount());
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
