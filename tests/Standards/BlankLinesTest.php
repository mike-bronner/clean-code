<?php

/**
 * Tests the custom CleanCode.WhiteSpace.BlankLines sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness, which
 * expressed its expectations as a line => error-count map keyed off the fixture
 * name. Every line those maps carried is preserved below, now with the column
 * and code of each report beside it; the fixtures also name what they exercise
 * instead of carrying a numeric suffix:
 *
 *   BlankLinesUnitTest.inc    => failing.php     (+ autofixed.php)
 *   BlankLinesUnitTest.2.inc  => after-open-tag-two-blanks.php
 *   BlankLinesUnitTest.3.inc  => after-open-tag-three-blanks.php
 *
 * The two open-tag fixtures are separate files rather than extra cases in
 * failing.php because the construct under test — blank lines between `<?php`
 * and the first statement — can only occur once per file.
 */

declare(strict_types=1);

const BLANK_LINES = 'CleanCode.WhiteSpace.BlankLines';

/**
 * Every violation failing.php carries, as line / column / message-code triples,
 * read off a live parse of the fixture rather than counted against its source.
 *
 * Both fixture-wide assertions below expand this one table. They were a count
 * map and a code map before #363 wired every column assertion through
 * violationTuples(); asserting a column makes each of them the whole tuple, so
 * they now overlap. The overlap is inherited from that split, and each stays its
 * own `it()` — the count check also pins that the fixture raises no warnings,
 * which the code check never asserted.
 *
 * Every column is 1: the sniff reports a blank line, which has no content for a
 * column to point into.
 *
 * @var array<int, array{0: int, 1: int, 2: string}>
 */
const BLANK_LINES_FAILING_SITES = [
    [41, 1, 'ConsecutiveBlankLines'],
    [48, 1, 'ConsecutiveBlankLines'],
    [52, 1, 'ConsecutiveBlankLines'],
    [60, 1, 'AfterOpeningBrace'],
    [71, 1, 'BeforeClosingBrace'],
    [76, 1, 'AfterOpeningBrace'],
    [78, 1, 'BeforeClosingBrace'],
    [83, 1, 'AfterOpeningBrace'],
    [87, 1, 'BeforeClosingBrace'],
    [94, 1, 'AfterOpeningBrace'],
    [98, 1, 'BeforeClosingBrace'],
    [104, 1, 'AfterOpeningBrace'],
    [106, 1, 'BeforeClosingBrace'],
    [110, 1, 'AfterOpeningBrace'],
    [112, 1, 'BeforeClosingBrace'],
    [117, 1, 'AfterOpeningBrace'],
    [122, 1, 'AfterOpeningBrace'],
    [131, 1, 'ConsecutiveBlankLines'],
    [135, 1, 'AfterOpeningBrace'],
    [142, 1, 'AfterOpeningBrace'],
    [144, 1, 'BeforeClosingBrace'],
    [148, 1, 'AfterOpeningBrace'],
    [152, 1, 'BeforeClosingBrace'],
    [157, 1, 'BeforeClosingBrace'],
    [165, 1, 'BeforeClosingBrace'],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BLANK_LINES);
});

/**
 * passing.php covers correctly spaced classes, interfaces, traits, enums,
 * closures, and a plain function — the containers whose braces the sniff
 * inspects. The open-tag case is deliberately not here: it can only occur once
 * per file, so it has its own two fixtures below.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BLANK_LINES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every superfluous blank line at its own line', function (): void {
    $file = analyzeFixture(BLANK_LINES, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $site): array => [
            'line' => $site[0],
            'column' => $site[1],
            'source' => BLANK_LINES . '.' . $site[2],
        ],
        BLANK_LINES_FAILING_SITES
    ))->and($file->getWarnings())->toBe([]);
});

/**
 * The two brace edges are driven from one table in checkBraces(), whose rows
 * carry the diagnostic's code and message alongside the scan geometry. Only
 * the geometry — start line, direction, limit — steers detection and the
 * fixer, so swapping the two rows' labels would leave every reported line,
 * every count and every auto-fixed byte identical while each violation named
 * the opposite edge. The codes are the sniff's public contract (a consumer
 * silences one edge with `phpcs:ignore
 * CleanCode.WhiteSpace.BlankLines.AfterOpeningBrace`), so they are pinned
 * here per line: a run below an opening brace reports AfterOpeningBrace, a run
 * above a closing brace reports BeforeClosingBrace, and a run touching
 * neither reports ConsecutiveBlankLines.
 */
it('labels every violation with the code for the edge it sits at', function (): void {
    $file = analyzeFixture(BLANK_LINES, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $site): array => [
            'line' => $site[0],
            'column' => $site[1],
            'source' => BLANK_LINES . '.' . $site[2],
        ],
        BLANK_LINES_FAILING_SITES
    ));
});

/**
 * The message half of the same table row, which a code-only assertion cannot
 * see: the opener wording lands on a run below an opening brace and the closer
 * wording on a run above a closing brace, each carrying its own blank-line
 * count. Both a single-line run (60, 71) and a multi-line one (122, 165) are
 * pinned, so a message swapped between the rows and a count read from the
 * wrong end are both caught.
 */
it('words every brace diagnostic for the edge it sits at', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(BLANK_LINES, 'failing.php')->getErrors());

    expect($messages[60])->toBe(['Expected no blank lines after an opening brace; found 1'])
        ->and($messages[122])->toBe(['Expected no blank lines after an opening brace; found 2'])
        ->and($messages[71])->toBe(['Expected no blank lines before a closing brace; found 1'])
        ->and($messages[165])->toBe(['Expected no blank lines before a closing brace; found 2']);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(BLANK_LINES, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('BlankLinesSniff', 'autofixed.php')));
});

/**
 * Blank lines directly after the opening `<?php` tag, which must sit at the
 * very start of a file. Two and three blank lines are covered separately: both
 * collapse to one, and each reports a single error on line 3 — the line the
 * run's *last* blank sits above in the two-blank case, and the middle of the
 * run in the three-blank case. A fixer that collapsed only the first pair
 * would leave the three-blank fixture unfixed.
 *
 * @param string $fixture      the fixture exercising the blank-line run
 * @param string $fixedFixture its expected auto-fixed output
 */
it('collapses blank lines after the open tag', function (string $fixture, string $fixedFixture): void {
    $file = analyzeFixture(BLANK_LINES, $fixture);

    expect(violationTuples($file))
        ->toBe([['line' => 3, 'column' => 1, 'source' => BLANK_LINES . '.ConsecutiveBlankLines']])
        ->and($file->getWarnings())->toBe([])
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('BlankLinesSniff', $fixedFixture)));
})->with([
    'two blank lines' => ['after-open-tag-two-blanks.php', 'after-open-tag-two-blanks.fixed.php'],
    'three blank lines' => ['after-open-tag-three-blanks.php', 'after-open-tag-three-blanks.fixed.php'],
]);
