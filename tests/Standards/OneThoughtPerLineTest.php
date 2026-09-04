<?php

/**
 * Tests the custom CleanCode.ClearCode.OneThoughtPerLine sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the line =>
 * error-count map below is preserved verbatim from that test's getErrorList().
 * Fixtures moved from CleanCode/Tests/ClearCode/OneThoughtPerLineUnitTest.inc
 * (+ .inc.fixed) to tests/fixtures/OneThoughtPerLineSniff/failing.php
 * (+ autofixed.php).
 *
 * Line 25 carries two errors — a chain broken across lines more than once in a
 * single statement — which is why it appears twice below, once per reported
 * column, rather than once per line.
 */

declare(strict_types=1);

const ONE_THOUGHT_PER_LINE = 'CleanCode.ClearCode.OneThoughtPerLine';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ONE_THOUGHT_PER_LINE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => ONE_THOUGHT_PER_LINE . '.MultipleAccessOperators',
        ],
        [
            [25, 23], [25, 32], [26, 25], [27, 23], [28, 27], [29, 26],
            [30, 27], [31, 14], [34, 16], [36, 26], [37, 26], [38, 25],
            [42, 28],
        ]
    ))->and($file->getWarnings())->toBe([]);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('OneThoughtPerLineSniff', 'autofixed.php')));
});

/**
 * The grouping exemption. An access operator held by an unclosed `(`, `[`, or
 * short-array opener is one term of a list the line already exists to hold, not
 * the line's own thought — so an argument list, an array rendered one item per
 * row, and a statement condition all stay compliant however many operators they
 * carry.
 *
 * The three negatives are what keep the exemption from swallowing the rule. A
 * closure body is inside the closure, not inside the argument list the closure
 * was passed to, so the search stops at the brace rather than reaching the
 * enclosing parentheses. And a group that closes *before* the operator —
 * `wrap($cart)->order->total` — never enclosed it at all.
 *
 * Non-vacuous by mutation: making isGrouped() return true always empties this
 * list; returning false always adds lines 4, 8, 9, 13 and 18, the five grouped
 * positions the fixture exists to keep unflagged.
 */
it('ignores access operators held inside parentheses or brackets', function (): void {
    $file = analyzeFixture(ONE_THOUGHT_PER_LINE, 'grouped.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => ONE_THOUGHT_PER_LINE . '.MultipleAccessOperators',
        ],
        [[21, 13], [24, 22], [29, 24], [33, 28]]
    ));
});
