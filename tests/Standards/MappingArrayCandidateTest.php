<?php

/**
 * Tests the custom CleanCode.Conditionals.MappingArrayCandidate sniff
 * (Conditionals: Mapping Arrays, #23, scoped by #163). Fixtures live in
 * tests/fixtures/MappingArrayCandidateSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: whether a
 * mapping array actually reduces complexity for a given chain is a judgement no
 * token walk can make. So there is no autofixed fixture, and the tests below
 * prove every reported violation is non-fixable.
 *
 * The sibling CleanCode.Conditionals.AvoidConditionals sniff warns on *every*
 * if/elseif in the same fixtures. That is deliberate overlap between two
 * standards, not a duplicate diagnostic: AvoidConditionals counts branches,
 * this sniff names the replacement. Every assertion here narrows the ruleset to
 * this sniff alone, so the two stay independent.
 */

declare(strict_types=1);

const MAPPING_ARRAY_CANDIDATE = 'CleanCode.Conditionals.MappingArrayCandidate';

const MAPPING_ARRAY_CANDIDATE_CHAIN = MAPPING_ARRAY_CANDIDATE . '.IfChain';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MAPPING_ARRAY_CANDIDATE);
});

/**
 * passing.php pairs the compliant form — a mapping array with a `??` fallback —
 * with one near-miss method per exclusion rule. Every method there reaches the
 * branch count and looks mapping-shaped; each breaks exactly one rule. Relaxing
 * any single rule therefore reddens this test rather than going unnoticed.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One warning per qualifying chain, at its leading `if` — never once per
 * branch. The six chains cover the two body forms (return and assignment),
 * both operand orders, both equality operators, a chain with no trailing
 * `else`, branch values that are constants and array literals rather than
 * inline scalars, and signed numeric literals.
 *
 * Line 91 is the signed-literal guard. A negative number is two tokens in PHP,
 * so a condition parser that counts tokens rather than reading operands drops
 * `$code === -1` as if it were a compound condition — silently, since nothing
 * else about the chain looks unusual. This is the tuple that catches that.
 */
it('warns once per qualifying chain, at its leading if', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 32, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 46, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 60, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 75, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 91, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(6);
});

/**
 * The message names the branch count and the subject variable, so a reader can
 * tell which chain is meant without re-deriving it. Asserted on the two chains
 * whose counts differ, because a hardcoded "3" would pass against the first.
 */
it('names the branch count and the subject variable', function (): void {
    $messages = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php')->getWarnings();

    expect($messages[20][9][0]['message'])->toContain('3 branches')->toContain('"$code"')
        ->and($messages[75][9][0]['message'])->toContain('4 branches')->toContain('"$kind"');
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a chain the sniff has no safe rewrite for — where the map should live
 * and what the missing-key fallback is are both decisions outside the chain.
 * getFixableCount() is used rather than violationFixableFlags(), which reads
 * getErrors() only and so would report an empty list whatever the fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect($file->getWarningCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a walk that only ever handled the braced,
 * merged-`elseif` layout failing.php is written in. PHP_CodeSniffer attaches
 * scope to a different token in each of the other spellings, so each is a
 * separate path through clauseExtent():
 *
 *   28 — spaced `else if`: the T_ELSE carries no scope, the trailing T_IF does
 *   39 — brace-less: no scope on any clause; bodies end at their semicolon
 *   49 — brace-less *and* spaced `else if`, the two exceptions at once
 *   59 — alternative syntax: scope opens on `:` and closes on the next clause
 *   70 — alternative syntax with no `else`, closing on `endif`
 *   83 — braced and brace-less clauses mixed inside one chain
 *   94 — `else` and its `if` on separate lines
 *  108 — comments between clauses, including one after a closing brace
 *  127 — the *inner* chain of a nested pair
 *  150 — a spaced `else if` chain four branches long
 *
 * Line 150 is the double-report guard. Every `else if`'s trailing `if` is
 * dispatched to process() in its own right and must stay silent, because the
 * chain is already reported at its head. Only a chain this long can prove the
 * guard is what keeps it silent: in the three-branch chain on line 28 the
 * trailing `if` heads two branches and the minimum-branch threshold would drop
 * it anyway. Here the tail is three branches, so a missing guard shows up as a
 * second tuple on line 152 — verified by deleting the guard and watching this
 * assertion fail.
 */
it('warns once on every continuation shape', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 28, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 39, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 49, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 59, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 70, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 83, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 94, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 108, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 127, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 150, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

/**
 * The outer chain of the nested pair on line 122 holds a whole `if` in its
 * first branch, so it is excluded — and its exclusion must not suppress the
 * inner chain, which qualifies on its own bodies. Line 127 is asserted above;
 * this pins the *absence* of 122, which the ordered list alone would not make
 * obvious to a reader.
 */
it('judges a nested chain on its own bodies, not its parent', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'shapes.php');

    expect(array_keys($file->getWarnings()))->not->toContain(122);
});

/**
 * A truncated file still reaches the sniff — PHP_CodeSniffer tokenizes it
 * rather than refusing it — and every clause-walk mechanism has its own way of
 * running off the end: the braced walk looks past a closing brace, the
 * alternative-syntax walk needs a scope_closer the tokenizer never assigned,
 * and the brace-less walk calls findEndOfStatement() past the last token. Each
 * must terminate and stay silent.
 *
 * truncated-value.php is the one that fails *loudly* rather than by stalling.
 * Its chain satisfies every rule but the last: the final statement is cut off
 * inside an array literal, so it never reaches a semicolon, while everything
 * before the cut is a legitimate value expression. Reading that expression
 * without first confirming the statement ended reports the chain — a warning on
 * a file PHP cannot parse. This is what pins the terminator check.
 *
 * The assertion is the pair (no violations, and the run finished at all): an
 * unterminated walk would hang here rather than fail.
 */
it('terminates silently on a truncated chain', function (string $fixture): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'truncated-braced.php',
    'truncated-alternative.php',
    'truncated-braceless.php',
    'truncated-value.php',
]);

/**
 * Deeply nested single-branch `if`s are the shape that makes this walk
 * expensive. Every `if` not preceded by `else` heads a chain in its own right,
 * and an outer clause's body range contains every level nested inside it — so a
 * walk that materialises a body before rejecting it pays one full pass per
 * level, and the file costs its own square.
 *
 * The nesting here is brace-less on purpose. It is the shape that stays
 * quadratic under the obvious half-fix: a brace-less body *does* end at a
 * semicolon, so checking the terminator alone still lets every level through to
 * findEndOfStatement(), whose own search is unbounded. Reading the body's first
 * token before asking for its end is what closes it.
 *
 * It is also the only shape that can nest this far. PHP_CodeSniffer abandons a
 * file whose braced scopes nest more than 50 deep — it throws "Maximum nesting
 * level reached" from Tokenizers/Tokenizer.php rather than tokenizing it — so
 * braced nesting has no reachable scale to measure, and its rejection is pinned
 * for correctness by the nested pair in shapes.php instead. A brace-less body
 * opens no scope, so nothing caps it and the walk is the only thing that can.
 *
 * Measured in this harness at 2400 levels when the reorder landed: 6.161s
 * before the walk was reordered, 0.123s after — and 0.349s / 1.345s / 3.202s
 * before at 600 / 1200 / 1800, the ~4x per doubling that names the growth as
 * quadratic. Those readings are kept as provenance for what the reorder is
 * worth; nothing here is timed any more.
 *
 * The claim is counted rather than timed (#354, extending #321). A wall-clock
 * budget states an asymptotic fix only as far as a shared CI runner allows —
 * #321 recorded the same assertion shape failing twice and passing on a third
 * run with no code change — so the reorder is read from
 * MappingArrayCandidateSniff::scanCounts(), as a delta around this one run:
 *
 * - `braceless.headRefusals`, incremented in clauseExtent()'s
 *   isStatementHead() branch, which is the reordered read: the body's first
 *   token decides the clause before its end is ever asked for.
 * - `braceless.endScans`, incremented immediately before the
 *   findEndOfStatement() call in that same method, which is the
 *   body-materialising walk the reorder avoids. Its search is unbounded, so one
 *   call per level is what the 6.161s above is made of.
 *
 * Both are asserted, and the pair is what makes the claim rather than either
 * alone: a refusal count could hold while the ends were asked for anyway
 * through some other path, and an end-scan count of 1 could equally mean the
 * file stopped being walked. Of the 2400 nested levels every one but the
 * innermost has another `if` for a body, so 2399 are refused on their first
 * token and exactly one — the innermost `return`, which is a statement head —
 * is walked to its end. No replacement bound is derived from the old 2.0s cap,
 * because none is needed: both counts are exact consequences of the fixture's
 * own 2400 levels, so they are asserted as `$levels - 1` and 1 rather than as a
 * budget with headroom.
 *
 * Mutation-checked by reverting the reorder so the end is asked for before the
 * body's first token is read; the diff hunk and the resulting failure are in
 * this PR's description.
 *
 * The warning assertion is what stops the counts from passing vacuously, and it
 * has already earned its keep: a file the tokenizer gives up on is both cheap
 * and silent about this sniff, which a stopwatch alone would have read as a
 * pass.
 */
it('stays linear on deeply nested chains', function (): void {
    $levels = 2400;
    [$source, $chainLine] = nestedChainFixture($levels);
    $fixture = stageGeneratedFixture('nested.php', $source);

    // buildRuleset() memoises the ruleset, and so the sniff instance, per
    // sniff-code key: this is the same instance every other test in this file
    // drives, so the counters are read as a delta rather than as a total.
    $sniff = sniffInstance(MAPPING_ARRAY_CANDIDATE);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([MAPPING_ARRAY_CANDIDATE], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect(warningTuples($file))->toBe([
        ['line' => $chainLine, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ])
        ->and($counted['braceless.headRefusals'])->toBe(
            ($levels - 1),
            'every nested level but the innermost is decided on its first token'
        )
        ->and($counted['braceless.endScans'])->toBe(
            1,
            'only the innermost body, which is a statement head, is walked to its end'
        );
});

/**
 * threshold.php holds a single two-branch chain and nothing else, so the
 * property is the only thing that can change the outcome between these two
 * assertions. The default keeps it silent; lowering the minimum to 2 reports
 * it, at the one line the fixture has to offer.
 *
 * The configured value is passed as the string PHP_CodeSniffer would hand over
 * from a ruleset `<property>` element, which is why the sniff's property is
 * untyped and cast where it is read.
 */
it('stays silent on a chain below the default minimum', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'threshold.php');

    expect($file->getWarnings())->toBe([]);
});

it('reports that same chain once minimumBranches is lowered', function (): void {
    $file = analyzeFixture(
        MAPPING_ARRAY_CANDIDATE,
        'threshold.php',
        static function (object $sniff): void {
            $sniff->minimumBranches = '2';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

/**
 * rules.xml claims no sniff already in the ruleset covers this standard. This
 * is what pins that claim: the whole of rules.xml, every sniff active, over the
 * failing fixture — and at each flagged chain head, exactly two sources.
 *
 * CleanCode.Conditionals.AvoidConditionals is the expected second one. It
 * counts the branch; this sniff names the replacement. Anything *third* would
 * mean some other rule had started delivering this diagnostic, and the custom
 * sniff would need re-evaluating rather than keeping the claim in a comment.
 *
 * Assertions are scoped to the five chain-head lines, per the helper's
 * contract, so unrelated additions to rules.xml cannot break them.
 */
it('leaves the chain to no other sniff in the ruleset', function (): void {
    $sources = allViolationSourcesByLine(
        analyzeWithMasterRuleset(fixturePath('MappingArrayCandidateSniff', 'failing.php'))
    );

    $atChainHeads = array_intersect_key($sources, array_flip([20, 32, 46, 60, 75, 91]));

    expect($atChainHeads)->toHaveCount(6);

    foreach ($atChainHeads as $line => $reported) {
        expect($reported)->toBe(
            ['CleanCode.Conditionals.AvoidConditionals.IfStatement', MAPPING_ARRAY_CANDIDATE_CHAIN],
            'unexpected sources on line ' . $line
        );
    }
});

/**
 * The sniff registers on T_IF alone, so `switch` and `match` are never
 * inspected whatever their shape. passing.php carries one of each written in
 * exactly the qualifying form — same subject, scalar literals, single-statement
 * arms — and the silence above already covers them. This pins the mechanism
 * rather than the outcome: widening register() to T_SWITCH or T_MATCH would
 * redden here even if someone also "fixed" the fixture.
 */
it('registers on if alone, so switch and match are never inspected', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[MAPPING_ARRAY_CANDIDATE]];

    expect($sniff->register())->toBe([T_IF]);
});
