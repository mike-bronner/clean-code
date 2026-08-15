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
 * The fixer's blast radius, as the complement of the round-trip the contract
 * sweep runs.
 *
 * The sweep proves failing.php fixes into autofixed.php byte for byte, which
 * pins what the fixer *did*. It cannot say that what changed was only the
 * condition lines. failing.php deliberately carries seven constructs that look
 * like groupings but are not — a `new class(...)` argument list, a `match`
 * subject, a closure parameter list, an arrow-function body, a comment line, a
 * heredoc body, and a wrapped double-quoted string — each at odd indentation
 * inside a file the fixer genuinely rewrites. Reindenting a heredoc body would
 * change a string's value rather than its layout; the rest would move code the
 * sniff has no business moving. Comparing the two fixtures line by line is
 * what proves none of that happened.
 */
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
 * The two implementations are a factor of ~17 apart at this depth — 0.06s for
 * the walk that jumps nested regions against 0.96s for the walk that stepped
 * through them, both measured in-process here — so the budget can sit an order
 * of magnitude above the linear time and still an order below the quadratic
 * one. That is what a single absolute threshold needs to be safe on a machine
 * of unknown speed, since both numbers scale together with the machine.
 *
 * The depth is capped at 600 by PHP_CodeSniffer itself, not by taste: past
 * roughly a thousand levels its tokenizer exhausts PHP's default 128M limit
 * while building the file, and a regression test that only runs under a raised
 * memory_limit is one nobody runs.
 *
 * The tuple assertion is what stops the timing passing vacuously: a walk that
 * gave up early, or a tokenizer that never got that far, would be both fast
 * and silent.
 */
it('stays linear as groupings nest', function (): void {
    $depth = 600;
    $lines = ['<?php', '', 'final class Scale', '{', '    public function nested(): void', '    {', '        if ('];

    for ($level = 0; $level < $depth; $level++) {
        // Every group but the outermost opens its first condition two spaces
        // shallow, so each level contributes exactly one violation.
        $indent = (12 + (4 * $level));
        $lines[] = str_repeat(' ', ($level === 0 ? $indent : ($indent - 2))) . '$this->a' . $level;
        $lines[] = str_repeat(' ', $indent) . '&& (';
    }

    $lines[] = str_repeat(' ', ((12 + (4 * $depth)) - 2)) . '$this->first';
    $lines[] = str_repeat(' ', (12 + (4 * $depth))) . '&& $this->second';

    for ($level = ($depth - 1); $level >= 0; $level--) {
        $lines[] = str_repeat(' ', (12 + (4 * $level))) . ')';
    }

    $lines = array_merge($lines, ['        ) {', '            $this->grant();', '        }', '    }', '}', '']);
    $fixture = stageGeneratedFixture('nested-groupings.php', implode("\n", $lines));

    buildRuleset([LOGICAL_GROUPINGS]);

    $started = hrtime(true);
    $file = analyzeWithSniffs([LOGICAL_GROUPINGS], $fixture);
    $elapsed = ((hrtime(true) - $started) / 1e9);

    $expected = [];

    for ($level = 1; $level < $depth; $level++) {
        $expected[] = [
            'line' => (8 + (2 * $level)),
            'column' => ((4 * $level) + 11),
            'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
        ];
    }

    $expected[] = [
        'line' => (8 + (2 * $depth)),
        'column' => ((4 * $depth) + 11),
        'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
    ];

    expect(violationTuples($file))->toBe($expected)
        ->and($elapsed)->toBeLessThan(0.5);
});

it('moves the reported condition lines and nothing else', function (): void {
    $before = file(fixturePath('LogicalGroupingsSniff', 'failing.php'));
    $after = file(fixturePath('LogicalGroupingsSniff', 'autofixed.php'));

    $changed = array_keys(array_filter(
        $before,
        static fn (string $line, int $index): bool => $line !== $after[$index],
        ARRAY_FILTER_USE_BOTH
    ));

    expect(array_map(static fn (int $index): int => ($index + 1), $changed))->toBe(
        array_column(violationTuples(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php')), 'line')
    );
});
