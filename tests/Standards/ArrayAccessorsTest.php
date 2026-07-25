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
 * failing.inc. Three fixtures cover input PHP itself would reject, which the
 * sniff must handle rather than fall over on: unterminated.inc for a bracket,
 * brace, or operator that never completes, trailing-variable.inc for a file
 * ending mid-statement on a bare variable, and malformed.inc for a statement
 * that parses to nonsense. tokenizer-limits.inc records a PHP_CodeSniffer
 * defect the sniff cannot see past. The rule is detection-only, so there are no
 * autofix fixtures.
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
     *
     * The `++` and `&` targets appear twice, plainly and behind a `$` sigil.
     * Both operators precede the chain, so they are only visible from the
     * chain's root — and a variable-variable roots at the sigil, not at the name
     * it dereferences. Rooting at the name hides both operators and flags two
     * writes as reads.
     *
     * One of those targets is deliberately a scope's last statement. Deciding
     * a `foreach` or destructuring target means looking past the chain for the
     * construct enclosing it, and there the search runs all the way to the
     * method's own closing brace. A scope brace and a dynamic member name
     * (`$order->{$name}`) are both curly braces to PHP_CodeSniffer, so
     * confusing the two would read the target as an offset and flag it.
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
                72 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                75 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                78 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                81 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                84 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                85 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                86 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                87 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                106 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                109 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                112 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                115 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                116 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                117 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                118 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                125 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                143 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                159 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                174 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                177 => [self::SNIFF_CODE . '.DirectArrayAccess'],
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
     *
     * Line 27 truncates a chain at the operator itself, leaving no member at
     * all. That is the same ambiguity as line 10's unclosed brace one step
     * earlier -- neither resolves to a property or a method call -- so the two
     * must agree, and reporting is the safe direction for a linter. Dropping it
     * would make the sniff quieter on the more truncated of the two.
     */
    public function testAnUnterminatedChainIsReportedWithoutFallingOver(): void
    {
        $file = $this->processFixture('unterminated.inc');

        $this->assertSame(
            [
                9 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                10 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
                19 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                27 => [self::SNIFF_CODE . '.DirectPropertyAccess'],
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
     * A write target names two chains when its offset is computed:
     * `$target[$payload['index']]` assigns into `$target` and *reads*
     * `$payload` to pick the slot. Only the outer chain is the target, so the
     * inner one stays flagged however the write is spelled.
     *
     * The distinction is only at risk where write-ness is inferred from a
     * construct *enclosing* the chain rather than from an adjacent token,
     * because an enclosing construct covers the offset as well as the target.
     * That is exactly the two paths pinned here — `foreach` targets and
     * destructuring patterns (failing.inc:72-87):
     *
     * - 72, `foreach ($rows as $target[$payload['value']])` — value target.
     * - 75, `foreach ($rows as $target[$payload['key']] => $ignored)` — key
     *   target, where `=>` marks the write rather than an array literal.
     * - 78, `foreach ($rows as $order->{$payload['member']})` — dynamic
     *   property target, whose offset sits in a brace rather than brackets.
     * - 84, `[$target[$payload['first']]] = $source` — short-array pattern.
     * - 85, `list($target[$payload['second']]) = $source` — `list()` pattern.
     * - 86, `['x' => $target[$payload['third']]] = $source` — keyed pattern.
     *
     * Lines 81 and 87 wrap the offset in `strtolower(...)`, once per path. The
     * enclosing construct nearest the read is then the call's `)`, not the
     * index `]`, so a search that gave up at the first construct it met would
     * miss the index and drop the read. The offset need not be the innermost
     * enclosing construct, and computing one with a call is ordinary.
     *
     * Each line asserts the count as well as the column, so the write half of
     * the pair is pinned too: a fix that flagged `$target`/`$order` as well
     * would report twice and fail here, as would one that dropped the read.
     */
    public function testComputedIndexesInsideWriteTargetsAreFlagged(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $columnsByLine = [
            72 => 35,
            75 => 35,
            78 => 36,
            81 => 46,
            84 => 18,
            85 => 22,
            86 => 25,
            87 => 29,
        ];

        foreach ($columnsByLine as $line => $column) {
            $this->assertCount(1, $errors[$line], "line {$line} reports once");
            $this->assertSame($column, array_key_first($errors[$line]), "line {$line} column");
        }
    }

    /**
     * The offset expression may carry braces and statements of its own, and
     * neither ends the accessor enclosing it: `match` arms, closures, and
     * anonymous classes are expressions, so a `}` is not the end of the
     * enclosing expression and a `;` inside one is not the end of the enclosing
     * statement. A search bounded by either takes the read for the target and
     * drops it — the whole of failing.inc:106-149 reported nothing before the
     * outward walk stopped terminating on them.
     *
     * Each gated path is covered, since only a path that infers write-ness from
     * an enclosing construct can lose the read this way:
     *
     * - 106/115/116, a `match` arm — `foreach` target, short-array and `list()`
     *   patterns.
     * - 117, `strtolower(match ...)` — the deciding index is not the innermost
     *   enclosing construct, and a `match` sits between it and the read.
     * - 109/112/118, a closure and a static closure whose bodies carry `return
     *   ...;` — the `;` that bounded the search before.
     * - 125, an anonymous class with a whole method body in the offset.
     * - 174/177, the dynamic-member column, whose offset sits in a brace.
     *
     * Counts are asserted alongside columns so the write half stays pinned:
     * flagging `$target`/`$order` too would report twice and fail here.
     */
    public function testOffsetsSpanningBracesAndStatementsAreFlagged(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $columnsByLine = [
            106 => 61,
            109 => 72,
            112 => 79,
            115 => 44,
            116 => 48,
            117 => 55,
            118 => 59,
            125 => 32,
            174 => 62,
            177 => 73,
        ];

        foreach ($columnsByLine as $line => $column) {
            $this->assertCount(1, $errors[$line], "line {$line} reports once");
            $this->assertSame($column, array_key_first($errors[$line]), "line {$line} column");
        }
    }

    /**
     * The nesting inverts as readily as it nests: a closure can put a whole
     * write statement *inside* an accessor's offset, so a `foreach` or
     * destructuring target sits within an index rather than around one. The
     * target is still a target, because the construct assigning into it
     * encloses it more tightly than the index does.
     *
     * boundaries.inc pins both directions (its assignsInsideAnAccessorOffset()
     * against the offset reads on failing.inc:106-149). Deciding by the
     * innermost enclosing construct is what keeps them apart: a walk that
     * asked only "is an index bracket anywhere outside me?" would answer yes
     * for the inverted shapes and flag a write, and one that asked only "is a
     * `foreach` anywhere outside me?" would drop every offset read.
     */
    public function testWriteTargetsNestedInsideAnAccessorOffsetAreNotFlagged(): void
    {
        $file = $this->processFixture('boundaries.inc');

        $this->assertSame([], $file->getErrors());
    }

    /**
     * Records a PHP_CodeSniffer defect the sniff cannot see past, so an
     * upstream fix surfaces as a failure here rather than silently.
     *
     * A `foreach` whose target is a dynamic member holding a brace-bearing
     * expression leaves the loop's scope unrecorded, and the tokenizer then
     * labels the *next* statement's destructuring pattern T_OPEN_SQUARE_BRACKET
     * instead of T_OPEN_SHORT_ARRAY. That label is the only thing separating an
     * index (a read) from a pattern (a write), so line 30's write target
     * `$target` is reported alongside the genuine read of `$payload` — two
     * violations where the same statement, after an ordinary dynamic member,
     * correctly yields one (line 43).
     *
     * It is pinned rather than worked around: the mislabelling happens before
     * any sniff runs, and reconstructing the distinction would mean re-deriving
     * it from token data already known to be wrong.
     */
    public function testATokenizerScopeDefectIsRecordedRatherThanWorkedAround(): void
    {
        $errors = $this->processFixture('tokenizer-limits.inc')->getErrors();

        $this->assertSame([10, 18], array_keys($errors[30]), 'the defect adds a false positive');
        $this->assertSame([18], array_keys($errors[43]), 'the same statement is correct without it');
    }

    /**
     * A statement PHP would reject must not suppress a read the sniff would
     * otherwise report. `doSomething($payload['key']) = $default` (line 11) is
     * a parse error — a call is not an assignable target — and only `list()`
     * parentheses close a destructuring pattern, so the trailing `=` says
     * nothing about the argument. It reports exactly as its well-formed
     * counterpart on line 12 does.
     *
     * The two lines differ in what makes the parentheses ineligible, which is
     * why both are here. Line 11's belong to an ordinary call and have no
     * owning construct at all; line 19's `if ($payload['key']) = $default`
     * *has* an owner, just not `list()`. Testing the owner's type rather than
     * its presence is the only thing keeping line 19's read reported — accept
     * any owned parenthesis and the `=` is read as assigning to the condition,
     * silently dropping it.
     */
    public function testAMalformedAssignmentStillReportsItsRead(): void
    {
        $file = $this->processFixture('malformed.inc');

        $this->assertSame(
            [
                11 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                12 => [self::SNIFF_CODE . '.DirectArrayAccess'],
                19 => [self::SNIFF_CODE . '.DirectArrayAccess'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * An existence check exempts what sits inside its own parentheses, not the
     * condition around them. `if (isset($payload['present']) && $payload['live']
     * === 'x')` (failing.inc:143) is the pairing that pins the scope: the
     * `isset()` argument is silent while the read beside it reports, which is
     * the read/exempt twin of testReadsBesideWritesAreStillFlagged.
     *
     * boundaries.inc covers the exempt half alone, so nothing there fails if the
     * check widens from "inside the isset parentheses" to "anywhere in the
     * enclosing condition". Here, widening it drops line 143 entirely.
     */
    public function testAReadBesideAnExistenceCheckIsStillFlagged(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $this->assertCount(1, $errors[143]);
        $this->assertSame(43, array_key_first($errors[143]));
    }

    /**
     * A variable-variable roots the chain at its `$` sigil, not at the name the
     * sigil dereferences: PHP 7's uniform variable syntax reads
     * `$$name['key']` (failing.inc:159) as `($$name)['key']`.
     *
     * The message is asserted, not just the position, because the subject is
     * the whole point. `$name` holds the *name* of the array, so advising
     * `data_get($name, ...)` would send the developer to search a string, which
     * always returns the fallback — advice that cannot be applied is worse than
     * none. Column 16 is the sigil; the variable begins at 17.
     */
    public function testAVariableVariableChainIsReportedAtItsSigil(): void
    {
        $errors = $this->processFixture('failing.inc')->getErrors();

        $this->assertCount(1, $errors[159]);
        $this->assertSame(16, array_key_first($errors[159]));
        $this->assertStringContainsString('data_get($$name, ...)', $errors[159][16][0]['message']);
    }

    /**
     * A file can end on a bare variable with no accessor after it to classify,
     * and the sniff must pass over it. The guard doing so is not decorative:
     * `readAccessCode()` takes an int, so without it the missing accessor
     * arrives as `false` and the sniff dies with a TypeError — this test errors
     * rather than fails if the guard is removed.
     */
    public function testAFileEndingOnABareVariableIsNotReported(): void
    {
        $file = $this->processFixture('trailing-variable.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Pins the detection-only decision: rewriting a chain to a dotted
     * `data_get()` path is a judgement call, so no violation is auto-fixable.
     */
    public function testViolationsAreDetectionOnly(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(39, $file->getErrorCount());
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
