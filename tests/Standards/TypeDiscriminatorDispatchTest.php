<?php

/**
 * Tests the custom CleanCode.Conditionals.TypeDiscriminatorDispatch sniff (the
 * Open-Closed slice of Pattern: SOLID, #5, scoped by #324). Fixtures live in
 * tests/fixtures/TypeDiscriminatorDispatchSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: whether a
 * given discriminator dispatch should have been polymorphism is a judgement no
 * token walk can make — some sit at a serialization boundary where polymorphism
 * has nowhere to attach. So there is no autofixed fixture, and the tests below
 * prove every reported violation is non-fixable.
 *
 * The sibling CleanCode.Conditionals.AvoidConditionals sniff warns on *every*
 * if/elseif and every switch in the same fixtures. That is deliberate overlap
 * between two standards, not a duplicate diagnostic: AvoidConditionals counts a
 * branch, this sniff names a pattern. Every assertion here narrows the ruleset
 * to this sniff alone, so the two stay independent.
 */

declare(strict_types=1);

const TYPE_DISCRIMINATOR_DISPATCH = 'CleanCode.Conditionals.TypeDiscriminatorDispatch';

const TYPE_DISCRIMINATOR_SWITCH = TYPE_DISCRIMINATOR_DISPATCH . '.SwitchDispatch';

const TYPE_DISCRIMINATOR_IF = TYPE_DISCRIMINATOR_DISPATCH . '.IfChain';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TYPE_DISCRIMINATOR_DISPATCH);
});

/**
 * passing.php pairs the compliant form — polymorphism — with one near-miss
 * method per exclusion rule. Each looks discriminator-shaped and breaks exactly
 * one rule: most reach the branch count and fail a shape rule, while the last
 * few fail the count itself — either on their own terms, or because the
 * branches that would carry them over belong to a second construct that merely
 * sits next to the first. Relaxing any single rule therefore reddens this test
 * rather than going unnoticed.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The same silence, named one exclusion at a time. The assertion above already
 * covers all of them together; this one says *which* near-miss broke when one
 * does, and pins the line each exclusion is written at so a fixture edit that
 * quietly drops a near-miss fails here instead of passing vacuously.
 */
it('stays silent on every near-miss shape', function (int $line): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'passing.php');

    expect(array_keys($file->getWarnings()))->not->toContain($line);
})->with([
    'plain-variable switch subject' => 74,
    'plain-variable if discriminator' => 86,
    'class constant as a case label' => 97,
    'bare constant as a case label' => 109,
    'variable as a case label' => 121,
    'variable as an if condition operand' => 144,
    'class constant as an if condition operand' => 162,
    'bare constant as an if condition operand' => 179,
    'non-literal operand written on the left' => 201,
    'compound boolean condition' => 214,
    'instanceof condition' => 225,
    'range condition' => 236,
    'not-identical condition' => 247,
    'called discriminator' => 258,
    'parenthesised condition' => 269,
    'two-case switch' => 280,
    'stacked pair below the threshold' => 292,
    'two-branch if chain' => 303,
    'match expression' => 312,
    'same property on two variables' => 321,
    'switch (true)' => 332,
    'index read two hops deep, switch' => 347,
    'index read two hops deep, if' => 362,
    'property read two hops deep, switch' => 373,
    'property read two hops deep, if' => 385,
    'positional index' => 399,
    'static property read' => 411,
    'two braced constructs merely adjacent' => 428,
    'three brace-less constructs merely adjacent' => 449,
    'nested brace-less if taking the continuations' => 464,
    'nested braced if taking the continuations' => 484,
    'nested if behind a brace-less loop' => 506,
    'nested if two brace-less loops in' => 529,
    'a swallowed clause on another discriminator' => 553,
    'a swallowed clause after a brace-less do/while' => 578,
]);

/**
 * One warning per qualifying construct, at the `switch` keyword or the leading
 * `if` — never once per `case` or `elseif`. The sixteen cover both constructs
 * against both discriminator shapes (object property and array index), the
 * literal written on either side of the comparison, `==` alongside `===`, a
 * nullsafe read, and the two counting rules a naive implementation gets wrong:
 * stacked labels sharing one body (line 112, three branches only if each label
 * counts on its own) and a `default` written first (line 123).
 *
 * Lines 166 and 182 are the counterpart to the nested-`if` near-misses in
 * passing.php: a brace-less clause whose body holds an `if` that a scope of its
 * own — a braced loop on line 166, a closure on line 182 — closes before the
 * body ends. Such an `if` can take no continuation, so both chains really do run
 * three branches deep. They are what stops the nested-`if` check from being
 * written as "any `if` in the body": drop its skip over scopes the body opens
 * and both of these go silent.
 *
 * Lines 210 and 233 are the opposite failure: a body holding a construct a
 * statement walk steps over whole — a braced loop, then a braced `switch` — so
 * a body boundary borrowed from that walk runs past the chain's own second
 * clause. Both chains really do run three branches deep as well; read the body
 * as a statement rather than walking it for a clause boundary, and both go
 * silent instead.
 *
 * Lines 256, 276 and 300 are the same failure in the other direction: a body
 * that is a statement PHP writes as more than one scope — `try`/`catch`,
 * `try`/`catch`/`finally`, `do`/`while` — where a boundary taken from the first
 * scope alone stops short of the body's real end and lands on `catch` or
 * `while`, neither of which continues a chain. All three run three branches
 * deep; stop the body walk at the first scope it steps over and all three go
 * silent.
 *
 * Line 322 is that same undershoot where no scope exists to step over: the
 * `do` is brace-less, so the body's own semicolon is the only boundary on
 * offer and it is the wrong one — the statement ends at the `while (…);`
 * after it. It is the one shape the step-over cannot reach, and `do` is the
 * only statement in PHP that has it.
 */
it('warns once per qualifying construct, at its head', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 56, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 73, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 85, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 99, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 112, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 123, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 135, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 149, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 166, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 182, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 210, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 233, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 256, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 276, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 300, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 322, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(16);
});

/**
 * The message has to do two things the AC names: state the principle by name,
 * so a reader knows which standard is speaking, and interpolate the
 * discriminator as it is written in the source, so they know which read is
 * meant without re-deriving it.
 *
 * Asserted on one switch and one if for each discriminator shape, because a
 * static message or a hardcoded example would pass against any single one of
 * them.
 */
it('names the principle and interpolates the discriminator', function (int $line, string $discriminator): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php')->getWarnings();

    expect($warnings[$line][9][0]['message'])
        ->toContain('Open-Closed')
        ->toContain('"' . $discriminator . '"');
})->with([
    'switch on a property' => [56, '$shape->type'],
    'switch on an index' => [73, "\$row['type']"],
    'if on a property' => [85, '$shape->type'],
    'if on an index' => [99, "\$row['type']"],
    'nullsafe property read' => [135, '$shape?->type'],
]);

/**
 * The counting rules, read back out of the message. Stacked labels sharing one
 * fallthrough body count one each, and a `default` counts as one wherever it
 * sits — both switches would be two branches under the opposite reading, which
 * is below the threshold, so the count in the message is the only place the
 * rule is visible rather than merely implied by the report existing.
 */
it('counts each case label and the default as one branch', function (int $line): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php')->getWarnings();

    expect($warnings[$line][9][0]['message'])->toContain('3 branches');
})->with([
    'stacked labels sharing one body' => 112,
    'default written first' => 123,
]);

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a dispatch the sniff has no safe rewrite for — introducing a type
 * hierarchy or a map, and rewriting every call site, is a design change.
 * getFixableCount() is used rather than violationFixableFlags(), which reads
 * getErrors() only and so would report an empty list whatever the fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect($file->getWarningCount())->toBe(16)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a walk that only ever handled the braced,
 * merged-`elseif` layout failing.php is written in. PHP_CodeSniffer attaches
 * scope to a different token in each of the other spellings, so each is a
 * separate path through nextClause():
 *
 *   23 — braced, merged `elseif`: the baseline both other fixtures use
 *   34 — spaced `else if`: the T_ELSE carries no scope, the trailing T_IF does
 *   45 — brace-less: no scope on any clause; bodies end at their semicolon
 *   55 — alternative syntax: scope opens on `:` and closes on the next clause
 *   66 — the switch's own alternative syntax, closing on `endswitch`
 *   85 — a spaced `else if` chain four branches long
 *
 * Line 85 is the double-report guard. Every `else if`'s trailing `if` is
 * dispatched to process() in its own right and must stay silent, because the
 * chain is already reported at its head. Only a chain this long can prove the
 * guard is what keeps it silent: in the three-branch chain on line 34 the
 * trailing `if` heads two branches and the minimum-branch threshold would drop
 * it anyway. Here the tail is three branches, so a missing guard shows up as a
 * second tuple on line 87.
 */
it('warns once on every continuation spelling', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 23, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 34, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 45, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 55, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 66, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 85, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

/**
 * nested-switch.php is what the arm walk's jump over a nested scope is written
 * for. Three shapes, each a switch that qualifies at every level:
 *
 *   18/20 — an outer switch of three arms holding a nested one of five
 *   45/51 — the same, with the nested switch written as the last statement of
 *           the outer's final arm, so its closing brace is the token before the
 *           outer's own. That is the boundary between the jump's target and the
 *           walk's `$pointer < $closer` termination, and an off-by-one either
 *           way loses the outer's report or runs the walk past its own switch
 *   68/70/72 — three levels deep, with three, four and five arms
 *
 * Every count is distinct from the counts around it. That is what makes the
 * numbers below a regression guard: arms leaking across a boundary would have
 * to change a reported number rather than summing to the same one by accident.
 */
it('reports each nested switch on its own arms alone', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'nested-switch.php');

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 20, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 45, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 51, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 68, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 70, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 72, 'column' => 21, 'source' => TYPE_DISCRIMINATOR_SWITCH],
    ]);
});

/**
 * The counts themselves, read back out of each message. The assertion above
 * says a report exists at each level; this one says the number it carries is
 * that level's own arm count and nothing else. A walk that counted a nested
 * switch's arms into the switch around it would report 8 on line 18, 7 on line
 * 45, and 12 on line 68.
 */
it('counts only its own arms at every level of nesting', function (int $line, int $branches): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'nested-switch.php')->getWarnings();
    $column = array_key_first($warnings[$line]);

    expect($warnings[$line][$column][0]['message'])->toContain($branches . ' branches');
})->with([
    'outer of an outer/nested pair' => [18, 3],
    'nested of an outer/nested pair' => [20, 5],
    'outer, nested switch closing last' => [45, 3],
    'nested written as the final arm' => [51, 4],
    'outermost of three levels' => [68, 3],
    'middle of three levels' => [70, 4],
    'innermost of three levels' => [72, 5],
]);

/**
 * PHP_CodeSniffer tokenizes a file it cannot parse rather than refusing it, so
 * every pointer this sniff reads off the scope map can be absent. Each fixture
 * below removes one of them while satisfying every other rule it can, so the
 * missing pointer is the only thing between the file and a report.
 *
 * Eight of the ten pin a specific guard, each confirmed by deleting that guard
 * and watching this test go red on that fixture alone:
 *
 *   truncated-switch.php      — the switch's own scope, which bounds the arm
 *                               walk; without the check the walk reads a
 *                               scope_closer the tokenizer never assigned
 *   malformed-case.php        — one `case` arm's scope_opener, which bounds its
 *                               label, the same way
 *   malformed-default.php     — the same pointer on a `default` arm. `default`
 *                               carries no label to bound, so the guard is the
 *                               only thing that stops an arm PHP cannot parse
 *                               from being counted as the branch that carries
 *                               the switch over the minimum
 *   truncated-braced.php      — the body a trailing `else` needs to be a branch
 *                               at all; without the check the dangling `else`
 *                               is counted, carrying a two-branch chain over
 *                               the minimum and reporting a file PHP rejects
 *   truncated-braceless.php   — the first token of a brace-less body, which a
 *                               clause cut off at its condition has none of;
 *                               without the check the body walk starts on a
 *                               pointer the tokenizer never assigned
 *   truncated-block.php       — the tokens that end a brace-less body without
 *                               ending a statement, reached here as the `}`
 *                               closing the function around the body; without
 *                               the check the walk reads on past it and takes
 *                               the `else` written after it as this chain's
 *                               third branch
 *   truncated-endif.php       — the same check reached at an alternative-syntax
 *                               `endif`. Its own `if` never closed, so it
 *                               carries no scope pointer to be recognised by
 *                               and only its keyword says the walk has left the
 *                               construct — which is why the check is a list of
 *                               tokens rather than a read of the scope map
 *
 * A sixth pins the guard the nested-scope jump carries:
 *
 *   truncated-nested-switch.php — a nested `switch` written with no body, so it
 *                               carries no scope_closer for the arm walk's jump
 *                               to land on while the switch around it keeps both
 *                               of its own. Without the isset() check the jump
 *                               assigns that absent closer to the walk's own
 *                               pointer, and the loop's increment turns the null
 *                               into 1 — restarting the walk at the top of the
 *                               file, reading tokens that belong to no arm of
 *                               this switch at all
 *
 * The other two cover an outcome rather than a guard, and are here because the
 * spellings they use are ones the sniff handles by name:
 *
 *   truncated-alternative.php — the alternative-syntax chain, whose clauses
 *                               carry no scope once the last one is cut off
 *   malformed-subject.php     — a switch subject that never closes. Its guard
 *                               is deliberately belt-and-braces: PHPCS leaves
 *                               parenthesis_closer present-but-null rather than
 *                               absent, so removing the check degrades the
 *                               subject read to an empty token list and this
 *                               file stays silent either way. The guard says so
 *                               up front instead of leaving the silence to
 *                               pointer arithmetic; the fixture pins the
 *                               outcome.
 *
 * The assertion is the pair (nothing reported, and the run finished at all): a
 * walk that reads an unassigned pointer raises a PHP warning, which
 * failOnWarning turns red, and one that never terminates hangs here.
 */
it('terminates silently on a file it cannot parse', function (string $fixture): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'malformed-subject.php',
    'malformed-case.php',
    'malformed-default.php',
    'truncated-switch.php',
    'truncated-braced.php',
    'truncated-braceless.php',
    'truncated-alternative.php',
    'truncated-block.php',
    'truncated-endif.php',
    'truncated-nested-switch.php',
]);

/**
 * threshold.php holds one two-branch `switch` and one two-branch `if` chain and
 * nothing else, so the property is the only thing that can change the outcome
 * between these two assertions. The default keeps both silent; lowering the
 * minimum to 2 reports both, at the two lines the fixture has to offer.
 *
 * The configured value is passed as the string PHP_CodeSniffer would hand over
 * from a ruleset `<property>` element, which is why the sniff's property is
 * untyped and cast where it is read.
 */
it('stays silent on constructs below the default minimum', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'threshold.php');

    expect($file->getWarnings())->toBe([]);
});

it('reports those same constructs once minimumBranches is lowered', function (): void {
    $file = analyzeFixture(
        TYPE_DISCRIMINATOR_DISPATCH,
        'threshold.php',
        static function (object $sniff): void {
            $sniff->minimumBranches = '2';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 15, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 27, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

/**
 * The shipped default, read off the instance CleanCode/ruleset.xml parsed rather than off
 * the class, so a `<properties>` block added there would have to be reflected
 * here. It is the value every assertion above depends on.
 */
it('ships minimumBranches at three', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TYPE_DISCRIMINATOR_DISPATCH]];

    expect($sniff->minimumBranches)->toBe(3);
});

/**
 * Every linearly nested brace-less `if` is a chain head in its own right — no
 * `else` sits before it — so PHP_CodeSniffer dispatches process() once per
 * level, and a brace-less clause carries no scope for the walk to jump. What
 * each of those calls costs is therefore the whole question. Re-deriving a
 * statement end per head walks the remaining levels every time: O(n) work paid
 * n times. Reading the body directly stops on the nested `if` that opens it,
 * one token in.
 *
 * Measured in this harness at 2000 / 4000 / 8000 levels: 1.69s / 6.66s /
 * 26.6s before the body walk replaced the statement boundary, the ~4x per
 * doubling that names it quadratic, and 0.17s / 0.29s / 0.55s after, which is
 * the file itself growing. A contributor's PR is a file a CI pipeline does not
 * control, and a few thousand nested clauses is a small one to write, so the
 * unbounded version turns a check into minutes of CPU.
 *
 * Those readings are kept as provenance for what the body walk is worth;
 * nothing here is timed any more. The claim is counted rather than timed (#354,
 * extending #321). A wall-clock budget states an asymptotic fix only as far as
 * a shared CI runner allows — #321 recorded the same assertion shape failing
 * twice and passing on a third run with no code change — so the walk is read
 * from TypeDiscriminatorDispatchSniff::scanCounts(), as a delta around this one
 * run. `bracelessNextClause.walks` is incremented where that method's own loop
 * is entered, and `bracelessNextClause.steps` as the first statement inside the
 * loop, so it counts exactly the tokens the loop visits.
 *
 * The pair is what states per-level cost, which neither number states alone: a
 * walk count says how often the method ran, and a step count says how far it
 * got, and only together do they say each run stopped one token in. Every one
 * of the $levels nested clauses is dispatched as its own chain head, so there
 * are $levels walks; each stops on the nested `if` that opens its body, one
 * step in, except the innermost, whose body is `return 1;` and takes three —
 * `return`, `1`, `;`. That is $levels + 2 steps in total, which is the direct
 * body read stated exactly. Re-deriving a statement end per head instead visits
 * the remaining levels every time and the step count becomes quadratic in
 * $levels. No replacement bound is derived from the old 3.0s cap, because none
 * is needed: both counts are exact consequences of the fixture's own $levels.
 *
 * Mutation-checked by removing the walk's stop on the nested `if`, so each head
 * runs on through the levels below it; the diff hunk and the resulting failure
 * are in this PR's description.
 *
 * The silence assertion is what stops the counts passing vacuously: a file
 * the sniff bailed out of early would also be cheap. Each level is one clause on
 * its own — PHP binds nothing to it — so no chain here reaches the minimum, and
 * a walk that instead read the levels as one chain would report at the first
 * `if` and redden this.
 */
it('stays linear on a deep stack of nested brace-less clauses', function (): void {
    $levels = 4000;
    $source = "<?php\n\nfunction deeplyNested(object \$shape): int\n{\n"
        . str_repeat("    if (\$shape->type === 'circle')\n", $levels)
        . "    return 1;\n}\n";
    $fixture = stageGeneratedFixture('nested-braceless.php', $source);

    // buildRuleset() memoises the ruleset, and so the sniff instance, per
    // sniff-code key: this is the same instance every other test in this file
    // drives, so the counters are read as a delta rather than as a total.
    $sniff = sniffInstance(TYPE_DISCRIMINATOR_DISPATCH);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([TYPE_DISCRIMINATOR_DISPATCH], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect($file->getWarnings())->toBe([])
        ->and($counted['bracelessNextClause.walks'])->toBe(
            $levels,
            'every nested clause is dispatched as a chain head of its own'
        )
        ->and($counted['bracelessNextClause.steps'])->toBe(
            ($levels + 2),
            'each walk stops one token into its body, the innermost `return 1;` in three'
        );
});

/**
 * The sniff registers on T_SWITCH and T_IF alone, so `match` is never inspected
 * whatever its shape — flagging it would have the ruleset argue with itself,
 * since CleanCode.Conditionals.MappingArrayCandidate and
 * CleanCode.Conditionals.AvoidConditionals both recommend `match` as the
 * replacement. passing.php carries a `match` written in exactly the qualifying
 * form, and the silence above already covers it. This pins the mechanism rather
 * than the outcome: widening register() to T_MATCH would redden here even if
 * someone also "fixed" the fixture.
 */
it('registers on switch and if alone, so match is never inspected', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TYPE_DISCRIMINATOR_DISPATCH]];

    expect($sniff->register())->toBe([T_SWITCH, T_IF]);
});
