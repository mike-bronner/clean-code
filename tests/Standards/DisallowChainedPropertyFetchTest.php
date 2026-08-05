<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Models.DisallowChainedPropertyFetch sniff
 * (Models: Relationship Properties, #42). Fixtures live in
 * Fixtures/DisallowChainedPropertyFetchSniff/ beside this file: the remedied
 * shape and every near-miss the sniff must leave alone in passing.inc, the
 * flagged chains in failing.inc, and a chain cut off mid-edit in
 * unterminated.inc.
 *
 * rules.xml scopes the sniff out of test paths, and these fixtures live under
 * tests/ — so processing one in place reports nothing whatever the sniff does.
 * Every assertion about the sniff's own behaviour therefore runs against a copy
 * staged outside the repository (processFixture()), and the exclusion itself is
 * pinned separately by testTheSniffIsScopedOutOfTestPaths(), which processes the
 * in-repo path and requires the silence to come from the path rather than from
 * the sniff having nothing to say.
 *
 * Most assertions isolate the sniff from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so they stay stable as
 * sibling standards land in rules.xml.
 * testTheSniffErrorsWhenRunThroughTheWholeMasterRuleset() deliberately does
 * not isolate: it is what pins the severity end to end.
 */
class DisallowChainedPropertyFetchTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Models.DisallowChainedPropertyFetch';

    private const ERROR_CODE = self::SNIFF_CODE . '.Found';

    private const FIXTURE_DIR = '/Fixtures/DisallowChainedPropertyFetchSniff/';

    /**
     * Every line of failing.inc that must carry exactly one diagnostic.
     *
     * @var array<int, int>
     */
    private const FAILING_LINES = [
        3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 18, 19, 20, 21, 22, 27, 33,
    ];

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
     * The remedied shape and every near-miss stay silent. Each group pins one
     * of the sniff's early returns, and a false positive on any of them makes
     * the rule unusable at error severity:
     *
     * - lines 3-5, `$book->authorName`, `$book->author`, `$this->title` — a
     *   single hop. The accessor attribute the standard mandates reads exactly
     *   like this, so flagging one hop would flag the remedy.
     * - lines 7-11, a method call anywhere in the chain — `->save()`,
     *   `->name()`, `->getAuthor()->name`, `->find($id)->name`,
     *   `->author()->name`. A call is a different access pattern, and only one
     *   property hop is left on either side of it in each of these.
     * - lines 13-15, `$a->b['x']->c`, `$a->b()['x']->c`, `$a->b->{$c}` — an
     *   array subscript ends the segment (its receiver is `]`, not a property
     *   name), and a braced member name is not a property name at all.
     * - lines 17-20, `Book::query()->first()->author->name`,
     *   `static::make()->author->name`, `self::$instance->author->name`,
     *   `Book::$registry->author->name` — static-rooted chains, out of scope
     *   for this issue. Each walks back past calls and property hops to a name
     *   preceded by `::`, and all four are needed: the last two prove the root
     *   test is not simply "the walk ended on a T_VARIABLE", since
     *   `$instance`/`$registry` are variables.
     * - lines 22-25, the negative half of the grouping-parenthesis walk.
     *   `($book)->author` is a single hop however it is parenthesised.
     *   `(new Book())->author->name` and `(Book::query()->first())->author->name`
     *   are an instantiation and a static root, neither of which becomes a
     *   variable root by being wrapped — the group is walked into, and what is
     *   found inside still decides. `foo($book)->author->name` is the
     *   discriminating pair to failing.inc:14: the parentheses there hold the
     *   root, the parentheses here are a call's argument list, so walking into
     *   them (and finding `$book`) would flag a function-call root the sniff
     *   has never claimed.
     * - lines 27-29, `$a->{$b}->c`, `$a->{'b'}->c`, `$a->$b->c` — a dynamic
     *   member name is not a property-fetch hop, because which property is
     *   read is unknowable at token level, so the single plain hop after it is
     *   not a chain. Both tokens a dynamic name can produce are covered: `{`
     *   for the two braced forms, T_VARIABLE for the plain-variable one.
     *   failing.inc:12 is the other half of this — two plain hops after a
     *   dynamic one *are* a chain.
     * - lines 31-37, an accessor declaration returning `$this->authorName` —
     *   the shape the standard asks for, in situ.
     */
    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Every chained fetch is flagged, once, at its own line:
     *
     * - line 3, `$book->author->name` — the standard's own example.
     * - lines 4-5, `$book?->author?->name` and `$book->author?->name` — the
     *   nullsafe operator is a separate token, so it has to be registered
     *   alongside the ordinary one, and mixing the two still counts.
     * - line 6, `$this->author->name` — a chain rooted in `$this` is still a
     *   chain. This is also the accessor's own body: the one place the
     *   standard's remedy has to traverse the relationship, so it is a known
     *   false positive that the doc's suppression path covers.
     * - line 7, `$book->author->address->city` — three hops earn one
     *   diagnostic, not two. The report lands on the pair that first completes
     *   the chain (`author->address`), so the developer is pointed at the
     *   first model rather than the last.
     * - line 8, `$order->customer->name ?? ''` — a null-coalescing default at
     *   the call site is the very duplication the accessor removes.
     * - line 9, `$a->b()->c->d` — a method call ends only its own segment;
     *   the two property hops after it form a chain of their own. The report
     *   names `c->d`, so it is the trailing pair being flagged and not the
     *   whole expression.
     * - line 10, `$a->b['x']->c->d` — the same reset via an array subscript.
     * - line 11, `$books[0]->author->name` — a subscripted variable is still a
     *   variable root; the walk has to step over the subscript to see it.
     * - line 12, `$book->{$relation}->address->city` — a braced member name is
     *   not itself a property hop, but the two hops after it are, and the root
     *   is still `$book`. Same rule as line 9: the unreadable hop ends its own
     *   segment only.
     * - lines 14-16, roots held inside a grouping parenthesis:
     *   `($book)->author->name`, `($condition ? $book : $fallback)->author->name`
     *   and `(($book))->author?->name`. A parenthesised variable is still a
     *   variable, so wrapping the root must not silence the chain — the walk
     *   has to look inside the group rather than at whatever precedes its
     *   opener (`=`, `?`, `:`, another `(`), and has to keep doing so through
     *   nesting. Both arms of the ternary are variables, which is why flagging
     *   it is right rather than a guess about which arm is taken.
     * - lines 18-22, the positive half of the same walk: every other bracketed
     *   group is stepped over, not walked into, and each line pins one of the
     *   ways that decision is reached. Lines 18-21 are argument lists, one per
     *   token that can sit in front of a call's opener — `$fn('author')`
     *   (T_VARIABLE), `($fn)()` (T_CLOSE_PARENTHESIS), `$handlers['x']()`
     *   (T_CLOSE_SQUARE_BRACKET), `$book->{$method}()`
     *   (T_CLOSE_CURLY_BRACKET) — all chains of two plain hops on a call
     *   result whose own root is a variable, the same reason line 9 is
     *   flagged. Dropping any one of those tokens reverts its line to walking
     *   into the argument list, where the root found is the argument rather
     *   than the callable. Line 22, `$book->{Book::KEY}->address->city`, is
     *   why walking in is restricted to parentheses in the first place: the
     *   braces hold a member name, not a receiver, so reading a root out of
     *   them finds `Book::KEY` and loses the real root, `$book`.
     * - line 27, a chain broken across lines with a comment in the middle —
     *   the layout the operator-line-break standard mandates must not hide it.
     * - line 33, the shape inside a controller that the standard is aimed at.
     */
    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame([], $file->getWarnings());
        $this->assertSame(
            array_fill_keys(self::FAILING_LINES, [self::ERROR_CODE]),
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The message names the offending pair so the developer knows which two
     * models to decouple, and names the remedy the standard prescribes — an
     * accessor attribute on the first model — rather than only stating that
     * something is wrong. Line 9 is asserted alongside line 3 because the pair
     * in the message is computed from the flagged hop, not from the start of
     * the expression: a message built from the whole statement would read
     * `a->b` there.
     */
    public function testTheErrorMessageNamesTheChainAndTheAccessorRemedy(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertStringContainsString('author->name', $this->firstMessageOnLine($file, 3));
        $this->assertStringContainsString('c->d', $this->firstMessageOnLine($file, 9));
        $this->assertStringContainsString(
            'getAuthorNameAttribute()',
            $this->firstMessageOnLine($file, 3)
        );
        $this->assertStringContainsString(
            'accessor attribute on the first model',
            $this->firstMessageOnLine($file, 3)
        );
    }

    /**
     * The standard mandates the accessor, so violations are errors — and they
     * have to survive the master ruleset as errors, not just leave the sniff
     * as one. This is the only test that runs the whole of rules.xml unedited:
     * a `<severity>` or `<type>` override on the rule, or an exclude-pattern
     * broader than the intended test-path one, would silence or demote the
     * sniff without touching the addError() call the other tests exercise.
     *
     * Filtering by source keeps the assertion about this sniff while the rest
     * of the ruleset reports whatever it likes about the same fixture.
     */
    public function testTheSniffErrorsWhenRunThroughTheWholeMasterRuleset(): void
    {
        $file = $this->processFile($this->stageOutsideTests('failing.inc'), null, false);

        $this->assertSame(
            array_fill_keys(self::FAILING_LINES, [self::ERROR_CODE]),
            $this->sourcesByLine($file->getErrors(), self::ERROR_CODE)
        );
        $this->assertSame([], $this->sourcesByLine($file->getWarnings(), self::ERROR_CODE));
    }

    /**
     * Test suites build object graphs inline and read straight through them,
     * so rules.xml scopes the sniff out of test paths. The exclusion is a path
     * match, so processing failing.inc where it actually lives — under tests/
     * — must report nothing, even though the same bytes produce an error on
     * every line of FAILING_LINES from outside the repository.
     *
     * Both halves are asserted together. The in-repo run alone would pass just
     * as well against a sniff that never fires at all, which is precisely the
     * failure mode the exclusion makes easy to ship unnoticed.
     */
    public function testTheSniffIsScopedOutOfTestPaths(): void
    {
        $inRepo = $this->processFile(__DIR__ . self::FIXTURE_DIR . 'failing.inc');

        $this->assertSame([], $inRepo->getErrors());
        $this->assertCount(
            count(self::FAILING_LINES),
            $this->processFixture('failing.inc')->getErrors()
        );
    }

    /**
     * PHP_CodeSniffer tokenizes files mid-edit, so a chain can end at the
     * operator with no member after it at all (line 4). The sniff has to pass
     * over it rather than fall over or invent a diagnostic for it.
     *
     * The `$memberPtr === false` guard that reads as what prevents this is in
     * fact defensive only, and removing it changes no result: PHP resolves
     * `$tokens[false]` to `$tokens[0]`, the open tag, which fails the T_STRING
     * check on the next line anyway. It is kept for saying so outright instead
     * of leaning on that coercion. Stated here because no fixture can pin it —
     * this test covers the truncated chain, not the guard.
     *
     * Two guards in the root walk are defensive in the same way, and are named
     * here rather than claimed as covered. isInvokedOn() returning false for an
     * opener with nothing before it cannot be reached — a file starts with its
     * open tag, so some token always precedes. Bounding the search inside a
     * grouping parenthesis at its opener only changes the result for a group
     * with no content, and `()->a->b` is not an expression PHP accepts.
     * Removing either changes no result on any fixture here.
     *
     * Line 3 keeps the assertion honest: the file still has to report the
     * complete chain that precedes the truncation, so a sniff that fell silent
     * on the whole file would fail here rather than pass.
     */
    public function testATruncatedChainIsHandledWithoutFallingOver(): void
    {
        $file = $this->processFixture('unterminated.inc');

        $this->assertSame([], $file->getWarnings());
        $this->assertSame([3 => [self::ERROR_CODE]], $this->sourcesByLine($file->getErrors()));
    }

    /**
     * At error severity an unsuppressed false positive breaks the build, so
     * the standard's doc publishes an inline suppression for the one traversal
     * it cannot avoid — the accessor's own body. Pins that the published
     * comment really does silence the sniff (line 8), and the unsuppressed
     * accessor below it (line 14) proves the silence comes from the comment
     * rather than from the sniff having nothing to say about the file.
     */
    public function testThePublishedInlineSuppressionSilencesTheSniff(): void
    {
        $file = $this->processFixture('suppressed.inc');

        $this->assertSame([14 => [self::ERROR_CODE]], $this->sourcesByLine($file->getErrors()));
    }

    /**
     * Pins the detection-only decision: fixing a flagged chain means authoring
     * an accessor method on the first model and choosing its default when the
     * relationship is absent, which cannot be synthesised from the tokens. No
     * violation is auto-fixable.
     */
    public function testViolationsAreDetectionOnlyErrors(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(count(self::FAILING_LINES), $file->getErrorCount());
        $this->assertSame(0, $file->getWarningCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    /**
     * Processes a fixture from a copy staged outside the repository, so
     * rules.xml's test-path exclusion does not silence the sniff before it
     * ever runs.
     */
    private function processFixture(string $fixture): LocalFile
    {
        return $this->processFile($this->stageOutsideTests($fixture));
    }

    /**
     * $isolate narrows the loaded ruleset to the sniff under test; pass false
     * to run the master ruleset exactly as a consumer would.
     */
    private function processFile(string $path, ?callable $configure = null, bool $isolate = true): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        if ($isolate === true) {
            // Isolate the sniff under test after the full ruleset has loaded
            // it. A $config->sniffs restriction cannot be used: under
            // PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip parsing rules.xml,
            // which is what pulls the custom CleanCode sniffs in.
            $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
            $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
            $ruleset->populateTokenListeners();

            if ($configure !== null) {
                $configure($ruleset->sniffs[$sniffClass]);
            }
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
        $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-chained-fetch-', true);

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

    private function firstMessageOnLine(LocalFile $file, int $line): string
    {
        foreach ($file->getErrors()[$line] ?? [] as $violations) {
            foreach ($violations as $violation) {
                if ($violation['source'] === self::ERROR_CODE) {
                    return (string) $violation['message'];
                }
            }
        }

        $this->fail("no {$line} line diagnostic from " . self::ERROR_CODE);
    }

    /**
     * Collapses PHPCS's line => column => violations structure to a map of
     * line number => list of violation source codes, optionally keeping only
     * one source.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, string>>
     */
    private function sourcesByLine(array $messages, ?string $only = null): array
    {
        $sources = [];

        foreach ($messages as $line => $columns) {
            foreach ($columns as $violations) {
                foreach ($violations as $violation) {
                    if ($only !== null && $violation['source'] !== $only) {
                        continue;
                    }

                    $sources[$line][] = $violation['source'];
                }
            }
        }

        ksort($sources);

        return $sources;
    }
}
