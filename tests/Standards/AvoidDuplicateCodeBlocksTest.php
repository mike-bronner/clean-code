<?php

/**
 * Tests the custom CleanCode.Pattern.AvoidDuplicateCodeBlocks sniff (partial
 * enforcement of Pattern: Don't Repeat Yourself (DRY), #134 out of #4).
 * Fixtures live in tests/fixtures/AvoidDuplicateCodeBlocksSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: the
 * standard tolerates duplication until an abstraction is warranted, so a
 * report is an abstraction *candidate*, not a defect. There is therefore no
 * autofixed fixture, and the tests below prove no report is fixable.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 *
 * Every threshold below is configured as a *string*, the way PHP_CodeSniffer
 * hands a ruleset's <property> value to a sniff — it passes on the text it read
 * out of the XML and never casts it. That is why the sniff's property is
 * untyped and cast where it is read, and why these tests set it the same way
 * rather than with an int a real ruleset could never deliver.
 */

declare(strict_types=1);

const AVOID_DUPLICATE_CODE_BLOCKS = 'CleanCode.Pattern.AvoidDuplicateCodeBlocks';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_DUPLICATE_CODE_BLOCKS);
});

/**
 * passing.php is four near-miss pairs the sniff walks in full and still leaves
 * alone: identical bodies too short to compare, a swapped operator, one extra
 * argument, and a `foreach` against a `while`.
 *
 * Each pair is the guard on a different half of the comparison. Widening the
 * match to ignore token *types* as well as token content reddens this on the
 * operator and argument pairs; counting brace-only lines towards the threshold
 * reddens it on the short pair.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The two shapes the standard is about, one warning each, reported at the
 * first line of the copy.
 *
 * Line 31 is the sub-declaration case and the reason this sniff compares
 * blocks rather than bodies: both copies sit inside one method, so no
 * whole-body comparison could ever see them. Line 62 is the whole-body case,
 * which block comparison still covers — the run simply happens to start at the
 * signature, because two same-shaped signatures are two same-shaped lines.
 *
 * Both copies rename variables and change literals, so comparing token content
 * would silence this test entirely.
 */
it('warns once per copied block, at the first line of the copy', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 31, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 62, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

/**
 * The warning sits on the copy, so it has to say where the original is or a
 * reader has nothing to compare against.
 */
it('names the line the copied block repeats', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php')->getWarnings();

    expect($messages[31][9][0]['message'])
        ->toContain('through line 37, repeats the block starting on line 21')
        ->and($messages[62][5][0]['message'])
        ->toContain('through line 69, repeats the block starting on line 52');
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(2);
});

/**
 * Detection only. Extracting shared logic and rewriting both call sites is a
 * design change, so a fixable count above zero would mean phpcbf had rewritten
 * something the sniff has no safe rewrite for. getFixableCount() is used
 * rather than violationFixableFlags(), which reads getErrors() only and so
 * would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect($file->getWarningCount())->toBe(2)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * boundaries.php holds an identical five-line pair and an identical four-line
 * pair. At the default of five only the first is reported.
 *
 * Both pairs are identical *including* their braces, which is what makes this
 * the test for the brace rule as well: counting brace-only lines would put the
 * four-line pair at six and report it here too.
 */
it('compares only blocks at or above the default line threshold', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'boundaries.php');

    expect(warningTuples($file))->toBe([
        ['line' => 25, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

/**
 * Lowering the threshold to four brings the four-line pair in, at line 45,
 * without disturbing the five-line one. The property is what decides, not a
 * constant baked into the walk.
 */
it('honours a lowered line threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'boundaries.php',
        static function (object $sniff): void {
            $sniff->minimumLines = '4';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 25, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 45, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

/**
 * floor.php carries four lines of code and is silent at the default: a file
 * shorter than one window has no window to compare.
 */
it('says nothing about a file shorter than one window', function (): void {
    expect(warningTuples(analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'floor.php')))->toBe([]);
});

/**
 * A configured zero floors to one, so the repeated single line arrives.
 *
 * The floor is what makes this test possible at all: without it a zero-line
 * window matches everywhere and never stops growing, and the walk — which
 * advances by the length of the block it just reported — runs backwards until
 * PHP exhausts memory. Removing `max(1, …)` does not redden this test so much
 * as kill the run outright.
 */
it('floors the line threshold at one', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'floor.php',
        static function (object $sniff): void {
            $sniff->minimumLines = '0';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[18][1][0]['message'])
        ->toContain('through line 18, repeats the block starting on line 16');
});

/**
 * A non-numeric threshold casts to zero and is then floored to one, so a
 * mistyped ruleset property reports more than intended rather than silently
 * reporting nothing. PHP_CodeSniffer hands a <property> value over as the raw
 * string it read, which is why the property is untyped: a native int
 * declaration would turn this into an uncatchable TypeError instead.
 */
it('reports more, not less, on a mistyped threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'floor.php',
        static function (object $sniff): void {
            $sniff->minimumLines = 'not-a-number';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

/**
 * The sniff registers on both PHP open tags and scans the whole file from the
 * first one, whichever kind it is, so every later tag must be ignored.
 * multiple-open-tags.php opens on a short echo tag and carries two plain ones
 * after it, around a single duplicated pair. Each half of that rule is
 * separately pinned here: dropping the guard reports line 30 three times,
 * dropping T_OPEN_TAG_WITH_ECHO from the guard's lookback reports it twice,
 * and dropping it from register() reports nothing at all.
 */
it('scans the file once however many open tags it carries', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'multiple-open-tags.php');

    expect(warningTuples($file))->toBe([
        ['line' => 30, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[30][1][0]['message'])
        ->toContain('through line 34, repeats the block starting on line 20');
});

/**
 * A run of same-shaped lines is one block, not a block repeating itself, and
 * neither half of that rule is free.
 *
 * seedCounters()'s nine lines stay silent because no five of them stand five
 * clear of another five; dropping the overlap guard reports them. seedLabels()
 * has twelve, so lines 47-51 do stand clear of 42-46 and are reported — but
 * only through line 51, because a sixth line would put the original at 42-47
 * and overlap the copy. Dropping the growth guard runs the same report on to
 * line 53.
 */
it('does not report a run of similar lines against itself', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'repetition.php');

    expect(warningTuples($file))->toBe([
        ['line' => 47, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[47][9][0]['message'])
        ->toContain('through line 51, repeats the block starting on line 42');
});
