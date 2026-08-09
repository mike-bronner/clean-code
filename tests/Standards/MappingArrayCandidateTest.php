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
 * branch. The five chains cover the two body forms (return and assignment),
 * both operand orders, both equality operators, a chain with no trailing
 * `else`, and branch values that are constants and array literals rather than
 * inline scalars.
 */
it('warns once per qualifying chain, at its leading if', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 32, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 46, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 60, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 75, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(5);
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

    expect($file->getWarningCount())->toBe(5)
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
]);

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

    $atChainHeads = array_intersect_key($sources, array_flip([20, 32, 46, 60, 75]));

    expect($atChainHeads)->toHaveCount(5);

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
