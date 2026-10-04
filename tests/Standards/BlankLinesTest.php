<?php

declare(strict_types=1);

const BLANK_LINES = 'CleanCode.WhiteSpace.BlankLines';

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
