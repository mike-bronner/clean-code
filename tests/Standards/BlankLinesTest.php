<?php

/**
 * Tests the custom CleanCode.WhiteSpace.BlankLines sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness, which
 * expressed its expectations as a line => error-count map keyed off the fixture
 * name. Those maps are preserved verbatim below; what changes is only that each
 * fixture now names what it exercises instead of carrying a numeric suffix:
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

    expect(violationCountsByLine($file->getErrors()))->toBe([
        41 => 1,
        48 => 1,
        52 => 1,
        60 => 1,
        71 => 1,
        76 => 1,
        78 => 1,
        83 => 1,
        87 => 1,
        94 => 1,
        98 => 1,
        104 => 1,
        106 => 1,
        110 => 1,
        112 => 1,
        117 => 1,
        122 => 1,
        131 => 1,
        135 => 1,
        142 => 1,
        144 => 1,
        148 => 1,
        152 => 1,
        157 => 1,
        165 => 1,
    ])->and($file->getWarnings())->toBe([]);
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

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        41 => [BLANK_LINES . '.ConsecutiveBlankLines'],
        48 => [BLANK_LINES . '.ConsecutiveBlankLines'],
        52 => [BLANK_LINES . '.ConsecutiveBlankLines'],
        60 => [BLANK_LINES . '.AfterOpeningBrace'],
        71 => [BLANK_LINES . '.BeforeClosingBrace'],
        76 => [BLANK_LINES . '.AfterOpeningBrace'],
        78 => [BLANK_LINES . '.BeforeClosingBrace'],
        83 => [BLANK_LINES . '.AfterOpeningBrace'],
        87 => [BLANK_LINES . '.BeforeClosingBrace'],
        94 => [BLANK_LINES . '.AfterOpeningBrace'],
        98 => [BLANK_LINES . '.BeforeClosingBrace'],
        104 => [BLANK_LINES . '.AfterOpeningBrace'],
        106 => [BLANK_LINES . '.BeforeClosingBrace'],
        110 => [BLANK_LINES . '.AfterOpeningBrace'],
        112 => [BLANK_LINES . '.BeforeClosingBrace'],
        117 => [BLANK_LINES . '.AfterOpeningBrace'],
        122 => [BLANK_LINES . '.AfterOpeningBrace'],
        131 => [BLANK_LINES . '.ConsecutiveBlankLines'],
        135 => [BLANK_LINES . '.AfterOpeningBrace'],
        142 => [BLANK_LINES . '.AfterOpeningBrace'],
        144 => [BLANK_LINES . '.BeforeClosingBrace'],
        148 => [BLANK_LINES . '.AfterOpeningBrace'],
        152 => [BLANK_LINES . '.BeforeClosingBrace'],
        157 => [BLANK_LINES . '.BeforeClosingBrace'],
        165 => [BLANK_LINES . '.BeforeClosingBrace'],
    ]);
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

    expect(violationCountsByLine($file->getErrors()))->toBe([3 => 1])
        ->and($file->getWarnings())->toBe([])
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('BlankLinesSniff', $fixedFixture)));
})->with([
    'two blank lines' => ['after-open-tag-two-blanks.php', 'after-open-tag-two-blanks.fixed.php'],
    'three blank lines' => ['after-open-tag-three-blanks.php', 'after-open-tag-three-blanks.fixed.php'],
]);
