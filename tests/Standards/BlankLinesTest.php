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
