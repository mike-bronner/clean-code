<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Models.DisallowExternalPersistenceCalls sniff
 * (Models: Persistence Methods (Repository Pattern), #37). Fixtures live in
 * Fixtures/DisallowExternalPersistenceCallsSniff/ beside this file: the
 * blessed $this-rooted persistence and the near-miss shapes the sniff must
 * leave alone in passing.inc, the flagged calls in failing.inc, and a pair of
 * calls whose verdicts swap with the configured method list in configured.inc.
 * The rule is detection-only, so there are no autofix fixtures.
 *
 * rules.xml scopes the sniff out of test paths, and these fixtures live under
 * tests/ — so processing one in place reports nothing whatever the sniff does.
 * Every assertion about the sniff's own behaviour therefore runs against a copy
 * staged outside the repository (processFixture()), and the exclusion itself is
 * pinned separately by testTheSniffIsScopedOutOfTestPaths(), which processes the
 * in-repo path and requires the silence to come from the path rather than from
 * the sniff having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class DisallowExternalPersistenceCallsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Models.DisallowExternalPersistenceCalls';

    private const WARNING_CODE = self::SNIFF_CODE . '.Found';

    private const FIXTURE_DIR = '/Fixtures/DisallowExternalPersistenceCallsSniff/';

    /**
     * Absolute paths of the fixture copies staged outside the repository, so
     * tearDown() can remove them.
     *
     * @var array<int, string>
     */
    private array $stagedPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->stagedPaths as $path) {
            if (is_file($path) === true) {
                unlink($path);
            }

            if (is_dir(dirname($path)) === true) {
                rmdir(dirname($path));
            }
        }

        $this->stagedPaths = [];

        parent::tearDown();
    }

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    /**
     * The blessed usage and every near-miss shape stay silent. Each group pins
     * one of the sniff's early returns, and a false positive on any of them
     * makes the rule unusable:
     *
     * - lines 3-6 and line 28, `$this->save()` and friends — the receiver
     *   check. This is the usage the standard mandates: a descriptive model
     *   method calling `$this->save()` at the end.
     * - lines 8-10, `User::create(...)`, `static::`, `parent::` — static calls
     *   carry no object operator at all, so the sniff never registers on them.
     *   Deliberately out of scope: `Model::create()` is token-indistinguishable
     *   from a named constructor or a factory API.
     * - lines 12-14, `saveQuietly()`, `updateOrFail()`, `persist()` — names
     *   that merely start with, extend, or paraphrase a configured name.
     * - lines 16-17, `$user->save` — a property read. Without the "next token
     *   is an open parenthesis" check these read as calls.
     * - lines 19-21, `$user->{$method}()`, `$user->{'save'}()` and
     *   `$user->$method()` — a dynamic member name is unknowable at token
     *   level. Every `->` reaches the sniff's T_STRING check; these are the
     *   three input shapes that the check actually *rejects*, and they cover
     *   both member tokens a dynamic name can produce: `{` for the two braced
     *   forms, T_VARIABLE for the plain variable one. They are silent either
     *   way — neither token's content is a legal PHP method name, so no
     *   configured name can equal it and removing the check changes no result
     *   here. The check is therefore a type guard rather than a behavioural
     *   branch, which is why no fixture can pin it; stated plainly rather than
     *   left to imply coverage.
     * - lines 31, 35 and 39, `create()`/`update()`/`delete()` method
     *   *declarations* — a model defining the very methods the sniff names
     *   must not flag itself.
     */
    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Every external persistence call is flagged, once, at its own line:
     *
     * - lines 3-6, the four shipped names on a plain variable receiver.
     * - line 7, `$user?->save()` — the nullsafe operator is a separate token
     *   and has to be registered alongside the ordinary one.
     * - line 8, `$USER->SAVE()` — PHP method names are case-insensitive, so
     *   the comparison is too.
     * - lines 9-10, `User::query()->firstOrFail()->delete()` and
     *   `Order::factory()->create()` — a chained receiver ends in `)`, not a
     *   variable, and the intervening `firstOrFail()` is not a configured name,
     *   so each line earns exactly one warning rather than none or two.
     * - line 11, `$user->delete(...)` — first-class callable syntax still opens
     *   a parenthesis, so it reads as a call.
     * - line 12, `$this->agent->save()` — the receiver is a *property of*
     *   `$this`, a different object, so persistence on it is external. The
     *   guard has to test the token immediately before the operator; a check
     *   for `$this` anywhere in the statement would drop this line.
     * - lines 18 and 23, calls inside a controller — the shape the standard is
     *   actually aimed at.
     */
    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame(
            [
                3 => [self::WARNING_CODE],
                4 => [self::WARNING_CODE],
                5 => [self::WARNING_CODE],
                6 => [self::WARNING_CODE],
                7 => [self::WARNING_CODE],
                8 => [self::WARNING_CODE],
                9 => [self::WARNING_CODE],
                10 => [self::WARNING_CODE],
                11 => [self::WARNING_CODE],
                12 => [self::WARNING_CODE],
                18 => [self::WARNING_CODE],
                23 => [self::WARNING_CODE],
            ],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * The message names the offending method, so a developer reading the report
     * knows which call to move into the model. `$USER->SAVE()` is asserted
     * because the comparison is case-insensitive while the message is not: the
     * source spelling has to survive into the output rather than the lowercased
     * copy the check works from.
     */
    public function testTheWarningMessageNamesTheOffendingMethod(): void
    {
        $warnings = $this->processFixture('failing.inc')->getWarnings();

        $this->assertStringContainsString('save()', $warnings[3][8][0]['message']);
        $this->assertStringContainsString('SAVE()', $warnings[8][8][0]['message']);
    }

    /**
     * The flagged names are a public sniff property, as the standard's doc
     * advertises. One fixture pins both directions with the same two lines:
     * under the shipped default `$user->save()` warns and
     * `$repository->persist()` does not, and once the list is replaced by
     * `persist` the verdicts swap. A property that was ignored would leave both
     * runs identical and fail the second assertion.
     */
    public function testThePersistenceMethodListIsConfigurable(): void
    {
        $shipped = $this->processFixture('configured.inc');

        $this->assertSame([4], array_keys($shipped->getWarnings()));

        $configured = $this->processFixture(
            'configured.inc',
            static function (object $sniff): void {
                $sniff->persistenceMethods = ['persist'];
            }
        );

        $this->assertSame([3], array_keys($configured->getWarnings()));
    }

    /**
     * Factory chains (`User::factory()->create()`) make generic CRUD calls
     * idiomatic in test suites, so rules.xml scopes the sniff out of test
     * paths. The exclusion is a path match, so processing failing.inc where it
     * actually lives — under tests/ — must report nothing, even though the same
     * bytes produce twelve warnings from outside the repository.
     *
     * Both halves are asserted together. The in-repo run alone would pass just
     * as well against a sniff that never fires at all, which is precisely the
     * failure mode the exclusion makes easy to ship unnoticed.
     */
    public function testTheSniffIsScopedOutOfTestPaths(): void
    {
        $inRepo = $this->processFile(__DIR__ . self::FIXTURE_DIR . 'failing.inc');

        $this->assertSame([], $inRepo->getWarnings());
        $this->assertCount(12, $this->processFixture('failing.inc')->getWarnings());
    }

    /**
     * PHP_CodeSniffer tokenizes files mid-edit, so a chain can end at the
     * operator with no member after it at all (line 6). The sniff has to pass
     * over it rather than fall over or invent a diagnostic for it.
     *
     * The `$methodPtr === false` guard that reads as what prevents this is in
     * fact defensive only, and removing it changes no result: PHP resolves
     * `$tokens[false]` to `$tokens[0]`, the open tag, which fails the T_STRING
     * check on the next line anyway. It is kept for saying so outright instead
     * of leaning on that coercion. Stated here because no fixture can pin it —
     * this test covers the truncated chain, not the guard.
     *
     * Lines 3-4 keep the assertion honest — the file still has to report the
     * calls that precede the truncation, so a sniff that fell silent on the
     * whole file would fail here rather than pass. Line 4 also records that a
     * reserved word is a legal method name and is flagged like any other:
     * PHP_CodeSniffer re-labels a reserved word following `->` as T_STRING, so
     * `list` reaches the name comparison exactly as `save` does.
     */
    public function testATruncatedChainIsHandledWithoutFallingOver(): void
    {
        $file = $this->processFixture(
            'unterminated.inc',
            static function (object $sniff): void {
                $sniff->persistenceMethods = ['save', 'list'];
            }
        );

        $this->assertSame([], $file->getErrors());
        $this->assertSame(
            [
                3 => [self::WARNING_CODE],
                4 => [self::WARNING_CODE],
            ],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * Pins the detection-only decision: rewriting `$user->save()` into a
     * descriptive model method is a judgement about meaning, so no violation is
     * auto-fixable.
     */
    public function testViolationsAreDetectionOnlyWarnings(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(12, $file->getWarningCount());
        $this->assertSame(0, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    /**
     * Processes a fixture from a copy staged outside the repository, so
     * rules.xml's test-path exclusion does not silence the sniff before it
     * ever runs. $configure, when given, receives the isolated sniff instance
     * so a test can set its public properties the way a consuming ruleset would.
     *
     * @param callable(object): void|null $configure
     */
    private function processFixture(string $fixture, ?callable $configure = null): LocalFile
    {
        return $this->processFile($this->stageOutsideTests($fixture), $configure);
    }

    /**
     * @param callable(object): void|null $configure
     */
    private function processFile(string $path, ?callable $configure = null): LocalFile
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

        if ($configure !== null) {
            $configure($ruleset->sniffs[$sniffClass]);
        }

        $file = new LocalFile($path, $ruleset, $config);
        $file->process();

        return $file;
    }

    /**
     * Copies a fixture to a temporary directory outside the repository and
     * returns the new path. PHPCS decides the test-path exclusion from the
     * file's path alone, so this is what lets the sniff see the fixture at all.
     */
    private function stageOutsideTests(string $fixture): string
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-persistence-', true);

        if (mkdir($directory, 0700) === false) {
            $this->fail("could not stage fixtures in {$directory}");
        }

        $path = $directory . '/' . $fixture;
        $this->stagedPaths[] = $path;

        copy(__DIR__ . self::FIXTURE_DIR . $fixture, $path);

        $this->assertStringNotContainsString(
            '/tests/',
            $path,
            'the staged fixture must sit outside any test path'
        );

        return $path;
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
