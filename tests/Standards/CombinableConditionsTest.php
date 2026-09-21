<?php

/**
 * Tests the custom CleanCode.Conditionals.CombinableConditions sniff
 * (Conditionals: Combine Where Possible, #21, scoped by #181). Fixtures live in
 * tests/fixtures/CombinableConditionsSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: merging
 * two conditions with `||` only pays off when the combined condition reads
 * better, and that is the judgement the standard leaves to a human. So there is
 * no autofixed fixture, and the tests below prove every reported violation is
 * non-fixable.
 *
 * Two shapes are flagged, and the difference between them is the point of most
 * of what follows:
 *
 *   - branches of one if/elseif chain, where identical bodies are always
 *     combinable, whatever the body does;
 *   - separate adjacent `if` statements, where they are combinable only if the
 *     shared body unconditionally exits the scope.
 *
 * The sibling CleanCode.Conditionals.AvoidConditionals sniff warns on *every*
 * if/elseif in these fixtures. That is deliberate overlap between two
 * standards: AvoidConditionals counts the branch, this sniff relates two
 * branches to each other. Every assertion here narrows the ruleset to this
 * sniff alone, except the one that exists to measure the overlap.
 */

declare(strict_types=1);

const COMBINABLE_CONDITIONS = 'CleanCode.Conditionals.CombinableConditions';

const COMBINABLE_CONDITIONS_CHAIN = COMBINABLE_CONDITIONS . '.ChainBranches';

const COMBINABLE_CONDITIONS_ADJACENT = COMBINABLE_CONDITIONS . '.AdjacentIfs';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMBINABLE_CONDITIONS);
});

/**
 * passing.php pairs the compliant form — the two conditions already joined with
 * `||` — with one near-miss method per exclusion rule. Every method there holds
 * two branches that look combinable and breaks exactly one rule, so relaxing
 * any single rule reddens this test rather than going unnoticed. The list of
 * which method pins which rule is in the fixture's own docblock.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One warning per participating branch, at that branch's own keyword.
 *
 * Lines 27 and 29 are the chain rule's whole point: the shared body only calls
 * a method, so an exit requirement applied to a chain would silence them. Lines
 * 42 and 44 pin adjacency inside a chain — the first branch differs and the
 * `else` on line 46 repeats the body but carries no condition, so neither joins
 * the group. Lines 54/56/58 and 136/140/144 are the runs of three: a walk that
 * stopped at the first matching pair would report two of each.
 *
 * The AdjacentIfs lines cover every exit statement the rule names — `return`
 * (68/72), `throw` (82/86), `exit` (94/98), `continue` (107/111) and `break`
 * (123/127) — because the check reads the body's last statement, and a list
 * that lost one of them would still pass on the other four. Lines 155 and 160
 * have a comment between the two `if`s, which is not a statement and so does
 * not break their adjacency.
 *
 * Lines 172/174 and 186/188 are the pairs whose chain continues into a branch
 * the sniff cannot read — a brace-less nested `if` for the first, a brace-less
 * loop for the second. Both pairs were themselves read in full, so both are
 * combinable and both are reported: an unreadable branch ends the chain at the
 * branch before it rather than voiding what came before. Reading it as fatal
 * silences all four of these lines while every other assertion here still
 * passes, which is why they are asserted at both spellings of "unreadable".
 */
it('warns once per participating branch', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 27, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 29, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 42, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 44, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 54, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 56, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 58, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 68, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 72, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 82, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 86, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 94, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 98, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 107, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 111, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 123, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 127, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 136, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 140, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 144, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 155, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 160, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 172, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 174, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 186, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 188, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(26);
});

/**
 * The message names the other members of the group by line, in both the
 * singular and the plural reading, and tells the reader what to do with them.
 * A message hardcoding "line" would pass on the pair and fail on the run of
 * three, which is why both are asserted.
 *
 * The `||` is asserted literally because the acceptance criteria ask for it by
 * name: the sniff points at a combination candidate, so the message has to say
 * what the combination is.
 */
it('names the other branches of the group and the operator to use', function (): void {
    $messages = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php')->getWarnings();

    expect($messages[27][9][0]['message'])
        ->toContain('the adjacent branch on line 29')
        ->toContain('"||"')
        ->and($messages[56][11][0]['message'])
        ->toContain('the adjacent branches on lines 54 and 58')
        ->and($messages[68][9][0]['message'])
        ->toContain('the adjacent "if" on line 72')
        ->toContain('exiting body')
        ->toContain('"||"')
        ->and($messages[140][9][0]['message'])
        ->toContain('the adjacent "if" statements on lines 136 and 144');
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently merged
 * two conditions — a rewrite whose whole value is whether the result reads
 * better, which is exactly what this sniff does not decide.
 * getFixableCount() is used rather than violationFixableFlags(), which reads
 * getErrors() only and so would report an empty list whatever the fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect($file->getWarningCount())->toBe(26)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a walk that only ever handled the braced,
 * merged-`elseif` layout failing.php is written in. PHP_CodeSniffer attaches
 * scope to a different token in each other spelling, so each is a separate path
 * through the clause walk:
 *
 *   20/22 — spaced `else if`: the T_ELSE carries no scope, the trailing T_IF does
 *   31/34 — the same, with `else` and its `if` on separate lines
 *   43/45 — alternative syntax: scope opens on `:` and closes on the next clause
 *   54/55 — brace-less separate `if`s, the canonical guard-clause spelling
 *   62/63 — alternative-syntax separate `if`s, each closing on its own `endif`
 *   70/72 — one chain mixing a braced clause with a brace-less one
 *   79/81 — an alternative-syntax `if` adjacent to a braced one
 *   91/93 — a chain nested inside another chain's branch
 *
 * Lines 70/72 and 79/81 are what pin bodies being compared rather than their
 * delimiters: `{ return 'same'; }` and `return 'same';` are the same body
 * written two ways, and a comparison that included the braces would miss both
 * pairs. Line 91 is the double-report guard for the nested pair: the outer `if`
 * on line 89 is not part of any group, and the inner chain is judged on its own
 * branches.
 */
it('warns on every continuation and body shape', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 22, 'column' => 16, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 31, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 34, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 43, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 45, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 54, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 55, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 62, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 63, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 70, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 72, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 79, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 81, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 91, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 93, 'column' => 15, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

/**
 * The `else:` branch of the alternative-syntax chain in shapes.php holds a
 * different body, and the outer `if` of the nested pair holds a whole chain —
 * neither can join a group. The ordered list above already excludes them, but
 * only by omission, which a reader has no reason to notice.
 */
it('leaves an else branch and a chain-holding outer branch alone', function (): void {
    $lines = array_keys(analyzeFixture(COMBINABLE_CONDITIONS, 'shapes.php')->getWarnings());

    expect($lines)->not->toContain(46)
        ->and($lines)->not->toContain(89);
});

/**
 * A truncated file still reaches the sniff — PHP_CodeSniffer tokenizes it
 * rather than refusing it — and each way of reading a body has its own way of
 * running off the end: the braced walk needs a scope_closer the tokenizer never
 * assigned, the brace-less walk calls findEndOfStatement() past the last token,
 * and an alternative-syntax clause missing its `endif` loses its scope
 * altogether and looks exactly like a brace-less clause whose body opens on a
 * colon. Each must terminate and stay silent.
 *
 * truncated-alternative.php is the one that fails *loudly* rather than by
 * stalling. Both its bodies are complete and identical, so reading the
 * scope-less clauses as brace-less ones reports a combinable chain in a file
 * PHP itself refuses to parse. That is what the colon check pins.
 *
 * The assertion is the pair (no violations, and the run finished at all): an
 * unterminated walk would hang here rather than fail.
 */
it('terminates silently on a truncated conditional', function (string $fixture): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'truncated-braced.php',
    'truncated-braceless.php',
    'truncated-alternative.php',
]);

/**
 * Silence is the right answer above only because nothing in those three files
 * could be read. It is the wrong answer when the truncation arrives *after* two
 * branches that were each read in full: the pair is combinable on its own
 * evidence, and a branch that never arrived is not evidence against it.
 *
 * This is the case the three fixtures above cannot make. Each of them cuts
 * where the very first clause is already unreadable, so a walk that voids a
 * whole chain the moment one clause fails and a walk that merely stops at that
 * clause are indistinguishable on all three — both stay silent. Here they
 * differ: voiding reports nothing, stopping reports the pair.
 */
it('still reports a comparable pair when the branch after it is truncated', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'truncated-after-comparable-pair.php');

    expect(warningTuples($file))->toBe([
        ['line' => 24, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 26, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

/**
 * Two shapes make this walk expensive, and the generated file holds both.
 *
 * A long run of adjacent identical guards is the first. The run is collected
 * once, from its head, and its members are then skipped by the file walk;
 * without that, every member would re-walk the rest of its own run and report
 * the group again from each position. The warning assertion catches the
 * duplicate reports and the clock catches the cost — the two failures of one
 * missing guard.
 *
 * Deeply nested brace-less `if`s are the second. A brace-less body has no
 * scope, so its extent comes from findEndOfStatement(), whose search runs to
 * the end of everything nested inside it — one walk per level, and the file
 * costs its own square. Refusing a body that opens another control structure,
 * before asking for its end, is what closes that; such a body can never be an
 * unconditional exit anyway. It is also the only shape that can nest this far:
 * PHP_CodeSniffer abandons a file whose braced scopes nest more than 50 deep
 * ("Maximum nesting level reached", Tokenizers/Tokenizer.php), while a
 * brace-less body opens no scope and nothing caps it.
 *
 * Both regressions were measured, not assumed, by making them: at 1200 guards
 * and 2400 levels this test takes 0.34s with both guards in place; with the
 * nesting refusal removed the same file measured 13.25s against the 3.0s
 * budget this test used to carry, and with the run dedupe removed it exhausts
 * PHP's 128 MB memory limit before finishing, because the run is also reported
 * once per member per position. Those readings are kept as provenance for what
 * the guards are worth; nothing here is timed any more.
 *
 * The claim is counted rather than timed (#354, extending #321). A wall-clock
 * budget states an asymptotic guard only as far as a shared CI runner allows —
 * #321 recorded the same assertion shape failing twice and passing on a third
 * run with no code change — so each guard is read from its own counter in
 * CombinableConditionsSniff::scanCounts(), as a delta around this one run:
 *
 * - `run.memberSkips`, incremented in process()'s `isset($grouped[$pointer])`
 *   branch. The 1200 adjacent guards are one run, collected from its head, so
 *   the walk skips the other 1199 members. Without the skip each member
 *   re-measures the rest of its own run, and the count reads 0.
 * - `braceless.nestingRefusals`, incremented in clauseExtent()'s
 *   NESTING_STATEMENTS branch. Each of the 2400 nested `if`s but the innermost
 *   has another `if` for a body, so 2399 are refused before their end is asked
 *   for.
 * - `braceless.endScans`, incremented immediately before the
 *   findEndOfStatement() call in that same method. That walk runs to the end of
 *   everything nested inside the body, so it is the cost the refusal avoids:
 *   with the refusal in place exactly one body — the innermost `return 0;` —
 *   reaches it. Removing the refusal moves 2399 refusals into 2399 further
 *   scans, which is what the 13.25s above is made of.
 *
 * The last two are the pair that makes the nesting claim, and they are asserted
 * together for that reason: a refusal count alone could hold while the scans
 * happened anyway through some other path, and a scan count alone could read 1
 * because the file stopped being walked at all. No replacement bound is
 * derived from the old 3.0s cap, because none is needed: the counts are exact
 * consequences of the fixture's own 1200 and 2400, so they are asserted as
 * `$size - 1`, `$depth - 1` and 1 rather than as a budget with headroom.
 *
 * Mutation-checked one guard at a time by reverting it and running
 * `composer test`; the diff hunks and the resulting failures are in this PR's
 * description. Removing the nesting refusal reddens `braceless.nestingRefusals`
 * (0 against 2399) and `braceless.endScans` (2400 against 1); removing the run
 * dedupe reddens `run.memberSkips` (0 against 1199).
 *
 * The warning assertion is what stops the counts from passing vacuously: a file
 * the tokenizer gives up on is both cheap and silent.
 */
it('stays linear on long runs and deep nesting', function (): void {
    $size = 1200;
    $depth = 2400;
    $lines = ['<?php', '', 'final class Scale', '{', '    public function guards(int $code): void', '    {'];
    $runStart = count($lines) + 1;

    for ($index = 0; $index < $size; $index++) {
        $lines[] = '        if ($code === ' . $index . ') {';
        $lines[] = '            return;';
        $lines[] = '        }';
    }

    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function nested(int $code): int';
    $lines[] = '    {';

    for ($level = 0; $level < $depth; $level++) {
        $lines[] = '        if ($code === ' . $level . ')';
    }

    $lines = array_merge($lines, ['        return 0;', '    }', '}', '']);
    $fixture = stageGeneratedFixture('scale.php', implode("\n", $lines));

    // buildRuleset() memoises the ruleset, and so the sniff instance, per
    // sniff-code key: this is the same instance every other test in this file
    // drives. Its counters are cumulative across all of them, which is why the
    // reading below is a delta rather than a total.
    $sniff = sniffInstance(COMBINABLE_CONDITIONS);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMBINABLE_CONDITIONS], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    $expected = [];

    for ($index = 0; $index < $size; $index++) {
        $expected[] = [
            'line' => $runStart + ($index * 3),
            'column' => 9,
            'source' => COMBINABLE_CONDITIONS_ADJACENT,
        ];
    }

    expect(warningTuples($file))->toBe($expected)
        ->and($counted['run.memberSkips'])->toBe(
            ($size - 1),
            "the run's other {$size} members are skipped by the walk, not re-measured"
        )
        ->and($counted['braceless.nestingRefusals'])->toBe(
            ($depth - 1),
            "every nested level but the innermost is refused before its end is asked for"
        )
        ->and($counted['braceless.endScans'])->toBe(
            1,
            'only the innermost body, which opens no control structure, is walked to its end'
        );
});

/**
 * CleanCode/ruleset.xml claims no sniff already in the ruleset covers this standard. This
 * is what pins that claim: the whole of CleanCode/ruleset.xml, every sniff active, over a
 * fixture holding one chain pair and one guard pair and nothing else — and at
 * each flagged line, exactly two sources.
 *
 * CleanCode.Conditionals.AvoidConditionals is the expected second one. It
 * counts the branch; this sniff relates two branches to each other.
 * CleanCode.Conditionals.DisallowElse.ElseIfFound is the expected third, on
 * line 22 only: with #14 the `elseif` keyword is itself a violation, which is
 * a diagnostic about one keyword rather than about two branches being
 * combinable. Anything *beyond* those would mean some other rule had started
 * delivering this sniff's diagnostic, and the custom sniff would need
 * re-evaluating rather than keeping the claim in a comment.
 *
 * failing.php cannot answer this question — its magic numbers, duplicate
 * blocks, and mapping-array-shaped chains trip four other sniffs at the same
 * lines — which is why ruleset-overlap.php exists.
 */
it('leaves the combinable conditional to no other sniff in the ruleset', function (): void {
    $sources = allViolationSourcesByLine(
        analyzeWithMasterRuleset(fixturePath('CombinableConditionsSniff', 'ruleset-overlap.php'))
    );

    expect(array_intersect_key($sources, array_flip([20, 22, 31, 35])))->toBe([
        20 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_CHAIN],
        22 => [
            'CleanCode.Conditionals.AvoidConditionals.ElseIfStatement',
            COMBINABLE_CONDITIONS_CHAIN,
            'CleanCode.Conditionals.DisallowElse.ElseIfFound',
        ],
        31 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_ADJACENT],
        35 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_ADJACENT],
    ]);
});

/**
 * The sniff runs once per file, from its first PHP open tag, because
 * combinability is a relation between statements rather than a property of one
 * token. Registering on the open tag rather than on T_IF is what lets a run of
 * adjacent `if`s be grouped and reported once — so this pins the mechanism, not
 * just the outcome.
 */
it('registers on the file open tags, so the whole file is walked once', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[COMBINABLE_CONDITIONS]];

    expect($sniff->register())->toBe([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]);
});
