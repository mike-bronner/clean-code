<?php

/**
 * Tests the custom CleanCode.Indentation.LogicalGroupings sniff.
 *
 * The generic floor — passing.php is clean, failing.php is flagged, the fixer
 * round-trips into autofixed.php and is idempotent — comes from
 * tests/Contract/SniffContractTest.php via the enumerations in tests/Sniffs.php,
 * so it is not restated here. What this file adds is what the sweep cannot see:
 * which line carries which of the two error codes, what the diagnostic claims
 * the expected indent is, and which lines the fixer is forbidden to move.
 */

declare(strict_types=1);

const LOGICAL_GROUPINGS = 'CleanCode.Indentation.LogicalGroupings';

const LOGICAL_GROUPINGS_NOT_INDENTED = LOGICAL_GROUPINGS . '.GroupNotIndented';

const LOGICAL_GROUPINGS_MISALIGNED = LOGICAL_GROUPINGS . '.MisalignedGroupedCondition';

/**
 * The failing-fixture lines whose fix is a line break rather than a reindent —
 * a group's first condition written on the group's own opening line. They are
 * the only lines that make autofixed.php longer than failing.php, so the
 * blast-radius test below re-joins them to compare the two line for line.
 */
const LOGICAL_GROUPINGS_GLUED_LINES = [402, 418];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LOGICAL_GROUPINGS);
});

/**
 * Every violation, pinned to its exact line, column, and code.
 *
 * The column is asserted alongside the line because the whole rule is about
 * columns: a violation reported on the right line at the wrong column would
 * mean the sniff measured a different token than the one it names.
 *
 * The two codes discriminate. A group's first condition is measured against
 * the line the group opens on and reports GroupNotIndented; every later
 * condition is measured against the group's own level and reports
 * MisalignedGroupedCondition. `misalignedCondition` is the fixture that
 * separates them — its first condition is already correct, so only its second
 * appears here, and a sniff that collapsed the two codes would fail this.
 *
 * The five control structures the standard names each contribute a pair, so
 * deleting a token type from register() removes its pair and reddens this too.
 */
it('flags every violation at its exact line, column, and code', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');

    expect(violationTuples($file))->toBe([
        // unindentedGroup — `if`
        ['line' => 18, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 19, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // tooShallowGroup
        ['line' => 31, 'column' => 15, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 32, 'column' => 15, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // tooDeepGroup
        ['line' => 44, 'column' => 21, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 45, 'column' => 21, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // misalignedCondition — first condition correct, so only the second is flagged
        ['line' => 58, 'column' => 19, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // nestedInnerGroupUnindented
        ['line' => 72, 'column' => 17, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 73, 'column' => 17, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // elseifUnindentedGroup
        ['line' => 88, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 89, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // whileUnindentedGroup
        ['line' => 101, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 102, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // forUnindentedGroup
        ['line' => 115, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 116, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // doWhileUnindentedGroup — the trailing `while` of a do-while
        ['line' => 131, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 132, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // wordOperatorUnindentedGroup — `or` / `and`, a separate PHPCS token set
        ['line' => 142, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 143, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // heredocOperandUntouched — the two real conditions only; body lines 243-245 are string content
        ['line' => 242, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 246, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // multiLineStringOperandUntouched — line 260 is string content, not a condition
        ['line' => 259, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 261, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // mixedArrowOperandUntouched — the two real conditions only; line 281 is the fn's body
        ['line' => 279, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 280, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // concatenatedGroupIndented — a grouping after `.`
        ['line' => 295, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 296, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // assignedGroupIndented — a grouping after `=`
        ['line' => 308, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 309, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // arrayValueOperandUntouched — the enclosing group only; lines 329-331 are the array
        ['line' => 327, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 328, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // arrayElementOperandUntouched — line 346 ends inside the array, so 347 is not a condition
        ['line' => 345, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 346, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // arrayOffsetOperandUntouched — line 363 is inside the subscript
        ['line' => 361, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 362, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // closureBodyOperandUntouched — lines 381-385 are the closure's body
        ['line' => 379, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 380, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        // gluedFirstConditionOnOpenerLine — the glued condition itself, at its
        // own column mid-line; its second condition is already at the level
        ['line' => 402, 'column' => 23, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        // gluedFirstConditionWithMisalignedSecond — the condition after a glued
        // first one is the group's second, so it is misaligned, not unindented
        ['line' => 418, 'column' => 23, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 419, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * A nested group's expected indent comes from the line its own parenthesis
 * opens on, not from a fixed depth or from the control structure.
 *
 * `nestedInnerGroupUnindented` puts the inner group inside a group that itself
 * sits at 16, so the inner conditions are owed 20. A sniff that measured every
 * group against the control structure would say 16 here — same line, same
 * code, different number — which the tuple assertion above cannot see. The
 * expected indent only appears in the rendered message, so that is what is
 * asserted.
 */
it('derives the expected indent from the immediate parent group', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');

    expect(violationMessagesByLine($file->getErrors())[72])->toBe([
        'Grouped condition must be indented one level deeper than its enclosing'
        . ' condition; expected 20 spaces, found 16',
    ]);
});

/**
 * A first condition written on the group's own opening line is the group's
 * first condition, and is given a line of its own.
 *
 * The walk that collects a group's condition lines records the first token of
 * each new line, so a condition glued to the opening parenthesis is only
 * collected if the walk starts from "no line yet" rather than from the
 * opener's line. Starting from the opener's line dropped it and promoted the
 * next line into its place, which is why both halves are asserted here: the
 * message proves the glued condition is reported at all and names the level it
 * is owed, and the fixed line proves the fixer broke the line instead of
 * rewriting the indentation of the line it shared — which would have moved the
 * enclosing condition. Line 419 in the tuple assertion above is the other half
 * of the promotion bug: it is a second condition, so it carries the misaligned
 * code, not the first-condition one.
 */
it('reports a first condition glued to the group opener, and gives it its own line', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');
    $fixed = file(fixturePath('LogicalGroupingsSniff', 'autofixed.php'));

    expect(violationMessagesByLine($file->getErrors())[LOGICAL_GROUPINGS_GLUED_LINES[0]])->toBe([
        'The first condition of a parenthesized group must start on its own line,'
        . ' indented one level deeper than its enclosing condition; expected 16 spaces',
    ])->and($fixed[LOGICAL_GROUPINGS_GLUED_LINES[0]])->toBe('                $this->isActive' . PHP_EOL);
});

/**
 * Nesting cost has to stay linear in the number of groups.
 *
 * Each nested group is checked in its own right, so a walk that measured a
 * group by stepping through every token between its parentheses would rescan
 * the whole condition once per level: n groups, n overlapping rescans,
 * quadratic. That is not a style problem here — this package is installed into
 * downstream lint pipelines that run over contributed code, so the cost of one
 * ordinary-looking file is CI CPU somebody else pays for.
 *
 * What is asserted is a ratio, not a stopwatch reading. A fixed budget in
 * seconds cannot do this job: the CI runner takes ~11x this machine's time for
 * the *linear* walk, which is already more than this machine spends on the
 * quadratic one, so any threshold safe there would be blind here.
 *
 * The baseline is the same file with the same parentheses nested to the same
 * depth, differing only in that each one opens a call rather than a grouping —
 * so the sniff skips them all and does no grouping work at all. That control
 * matters: PHP_CodeSniffer's own tokenizer is superlinear in parenthesis
 * depth, and on the CI runner it costs several times what this sniff does, so
 * a baseline that nested its parentheses less deeply would measure the
 * tokenizer and call it a regression. Holding the depth identical puts that
 * cost on both sides of the ratio, leaving the sniff's own work as the only
 * difference.
 *
 * The two implementations sit an order of magnitude either side of the
 * threshold: 1.06x for the walk that jumps each nested region against 15.5x
 * for the walk that stepped through it (0.06s/0.05s against 0.93s/0.06s,
 * measured in-process here).
 *
 * The count is capped at 600 by PHP_CodeSniffer itself, not by taste: past
 * roughly a thousand levels of nesting its tokenizer exhausts PHP's default
 * 128M limit while building the file, and a regression test that only runs
 * under a raised memory_limit is one nobody runs.
 *
 * The violation assertions are what stop the timings passing vacuously: a walk
 * that gave up early, or a tokenizer that never got that far, would be both
 * fast and silent. The baseline's own assertion is the complement — it has to
 * report nothing, or it is not the no-grouping-work control it is used as.
 */
it('stays linear as groupings nest', function (): void {
    $count = 600;

    // The same file either way: $count parentheses nested to the same depth,
    // opened by `&& (` for the groupings and by `&& check(` for the control.
    $build = function (string $opener) use ($count): string {
        $lines = ['<?php', '', 'final class Scale', '{', '    public function nested(): void', '    {'];
        $lines[] = '        if (';

        for ($level = 0; $level < $count; $level++) {
            // Every group but the outermost opens its first condition two
            // spaces shallow, so each level contributes exactly one violation.
            $indent = (12 + (4 * $level));
            $lines[] = str_repeat(' ', ($level === 0 ? $indent : ($indent - 2))) . '$this->a' . $level;
            $lines[] = str_repeat(' ', $indent) . $opener;
        }

        $lines[] = str_repeat(' ', ((12 + (4 * $count)) - 2)) . '$this->first';
        $lines[] = str_repeat(' ', (12 + (4 * $count))) . '&& $this->second';

        for ($level = ($count - 1); $level >= 0; $level--) {
            $lines[] = str_repeat(' ', (12 + (4 * $level))) . ')';
        }

        $tail = ['        ) {', '            $this->grant();', '        }', '    }', '}', ''];

        return implode("\n", array_merge($lines, $tail));
    };

    buildRuleset([LOGICAL_GROUPINGS]);

    $measure = function (string $name, string $source): array {
        $fixture = stageGeneratedFixture($name, $source);
        $started = hrtime(true);
        $file = analyzeWithSniffs([LOGICAL_GROUPINGS], $fixture);

        return [((hrtime(true) - $started) / 1e9), violationTuples($file)];
    };

    [$grouped, $groupedViolations] = $measure('nested-groupings.php', $build('&& ('));
    [$skipped, $skippedViolations] = $measure('nested-calls.php', $build('&& check('));

    $expected = [];

    for ($level = 1; $level < $count; $level++) {
        $expected[] = [
            'line' => (8 + (2 * $level)),
            'column' => ((4 * $level) + 11),
            'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
        ];
    }

    $expected[] = [
        'line' => (8 + (2 * $count)),
        'column' => ((4 * $count) + 11),
        'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
    ];

    expect($groupedViolations)->toBe($expected)
        ->and($skippedViolations)->toBe([])
        ->and($grouped)->toBeLessThan(($skipped * 4));
});

/**
 * A nested region whose closer PHP_CodeSniffer never recorded stops every walk
 * in the class, not just the two that already stopped.
 *
 * The source below is genuinely unparsable — an unterminated `[` inside a
 * validly-closed grouping parenthesis — so the tokenizer records a
 * `parenthesis_closer` for the `(` and no `bracket_closer` for the `[`. A walk
 * that treats "no recorded closer" as "not a nested region" then reads the
 * subscript's interior as the condition's own tokens: the `&&` in there makes
 * the parenthesis look like a logical grouping, and the subscript line — which
 * is not a condition at all — is reported and reindented by phpcbf.
 *
 * It cannot be a fixture file. The contract sweep runs every fixture through
 * phpcs expecting a clean parse, so unparsable source is staged for this test
 * alone and purged after it.
 *
 * Both halves are asserted because they fail independently: silencing the
 * report without stopping the walk would still let the fixer move the line.
 *
 * Two shapes, because the three walks stop at different moments and each owns a
 * different consequence. In the first the unresolved `[` sits before the only
 * boolean, so the walk testing for a top-level boolean reaches it first and the
 * parenthesis is never classified as a grouping at all. In the second a real
 * top-level boolean comes first, so the grouping *is* classified and the walk
 * measuring its condition lines is the one that has to stop — one line further
 * on, inside the subscript, at an indent it would otherwise rewrite.
 */
it('classifies nothing inside a nested region whose closer was never recorded', function (string $source): void {
    $file = analyzeWithSniffs([LOGICAL_GROUPINGS], stageGeneratedFixture('unresolved-region.php', $source));

    expect(violationTuples($file))->toBe([])
        ->and(autofixedContents($file))->toBe($source);
})->with([
    'unresolved region before the group\'s first boolean' => [
        <<<'PHP'
        <?php

        class Unresolved
        {
            public function run(array $data, bool $a, bool $b, bool $c): bool
            {
                if (
                    $a
                    || (
                            $data[
                        $b && $c
                    )
                ) {
                    return true;
                }

                return false;
            }
        }

        PHP,
    ],
    'unresolved region after it' => [
        <<<'PHP'
        <?php

        class Unresolved
        {
            public function run(array $data, bool $a, bool $b, bool $c, bool $d): bool
            {
                if (
                    $a
                    || (
                        $b
                        && $c
                        && $data[
                                    $d
                    )
                ) {
                    return true;
                }

                return false;
            }
        }

        PHP,
    ],
]);

/**
 * The fixer's blast radius, as the complement of the round-trip the contract
 * sweep runs.
 *
 * The sweep proves failing.php fixes into autofixed.php byte for byte, which
 * pins what the fixer *did*. It cannot say that what changed was only the
 * condition lines. failing.php deliberately carries eleven constructs that look
 * like groupings but are not — a `new class(...)` argument list, a `match`
 * subject, a closure parameter list, an arrow-function body, a comment line, a
 * heredoc body, a wrapped double-quoted string, and the four bracketed regions
 * that sit below the condition rather than in it (an array value, an array
 * element, a subscript, and a statement in a closure body) — each at odd
 * indentation inside a file the fixer genuinely rewrites. Reindenting a heredoc
 * body would change a string's value rather than its layout; the rest would
 * move code the sniff has no business moving. Comparing the two fixtures line
 * by line is what proves none of that happened.
 *
 * One fix breaks a line instead of reindenting it — the one that gives a
 * group's glued first condition a line of its own — so the two files carry
 * different line counts. Each of those pairs is re-joined first, which puts
 * the files back on one numbering without hiding anything: a re-joined line
 * still differs from the original it is compared against, so it is still
 * counted as changed, and it is still required to be a reported line.
 */
it('moves the reported condition lines and nothing else', function (): void {
    $before = file(fixturePath('LogicalGroupingsSniff', 'failing.php'));
    $after = file(fixturePath('LogicalGroupingsSniff', 'autofixed.php'));

    // Each splice removes the shift the one before it introduced, so every
    // line number below indexes the same line it names in failing.php.
    foreach (LOGICAL_GROUPINGS_GLUED_LINES as $line) {
        $index = ($line - 1);
        $joined = rtrim($after[$index], "\r\n") . $after[($index + 1)];

        array_splice($after, $index, 2, [$joined]);
    }

    expect($after)->toHaveCount(count($before));

    $changed = array_keys(array_filter(
        $before,
        static fn (string $line, int $index): bool => $line !== $after[$index],
        ARRAY_FILTER_USE_BOTH
    ));

    expect(array_map(static fn (int $index): int => ($index + 1), $changed))->toBe(
        array_column(violationTuples(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php')), 'line')
    );
});
