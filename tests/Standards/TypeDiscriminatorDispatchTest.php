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
    'plain-variable switch subject' => 70,
    'plain-variable if discriminator' => 82,
    'class constant as a case label' => 93,
    'bare constant as a case label' => 105,
    'variable as a case label' => 117,
    'variable as an if condition operand' => 140,
    'class constant as an if condition operand' => 158,
    'bare constant as an if condition operand' => 175,
    'non-literal operand written on the left' => 197,
    'compound boolean condition' => 210,
    'instanceof condition' => 221,
    'range condition' => 232,
    'not-identical condition' => 243,
    'called discriminator' => 254,
    'parenthesised condition' => 265,
    'two-case switch' => 276,
    'stacked pair below the threshold' => 288,
    'two-branch if chain' => 299,
    'match expression' => 308,
    'same property on two variables' => 317,
    'switch (true)' => 328,
    'index read two hops deep, switch' => 343,
    'index read two hops deep, if' => 358,
    'property read two hops deep, switch' => 369,
    'property read two hops deep, if' => 381,
    'positional index' => 395,
    'static property read' => 407,
    'two braced constructs merely adjacent' => 424,
    'three brace-less constructs merely adjacent' => 445,
    'nested brace-less if taking the continuations' => 460,
    'nested braced if taking the continuations' => 480,
    'nested if behind a brace-less loop' => 502,
    'nested if two brace-less loops in' => 525,
    'a swallowed clause on another discriminator' => 549,
]);

/**
 * One warning per qualifying construct, at the `switch` keyword or the leading
 * `if` — never once per `case` or `elseif`. The fifteen cover both constructs
 * against both discriminator shapes (object property and array index), the
 * literal written on either side of the comparison, `==` alongside `===`, a
 * nullsafe read, and the two counting rules a naive implementation gets wrong:
 * stacked labels sharing one body (line 108, three branches only if each label
 * counts on its own) and a `default` written first (line 119).
 *
 * Lines 162 and 178 are the counterpart to the nested-`if` near-misses in
 * passing.php: a brace-less clause whose body holds an `if` that a scope of its
 * own — a braced loop on line 162, a closure on line 178 — closes before the
 * body ends. Such an `if` can take no continuation, so both chains really do run
 * three branches deep. They are what stops the nested-`if` check from being
 * written as "any `if` in the body": drop its skip over scopes the body opens
 * and both of these go silent.
 *
 * Lines 206 and 229 are the opposite failure: a body holding a construct a
 * statement walk steps over whole — a braced loop, then a braced `switch` — so
 * a body boundary borrowed from that walk runs past the chain's own second
 * clause. Both chains really do run three branches deep as well; read the body
 * as a statement rather than walking it for a clause boundary, and both go
 * silent instead.
 *
 * Lines 252, 272 and 296 are the same failure in the other direction: a body
 * that is a statement PHP writes as more than one scope — `try`/`catch`,
 * `try`/`catch`/`finally`, `do`/`while` — where a boundary taken from the first
 * scope alone stops short of the body's real end and lands on `catch` or
 * `while`, neither of which continues a chain. All three run three branches
 * deep; stop the body walk at the first scope it steps over and all three go
 * silent.
 */
it('warns once per qualifying construct, at its head', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 52, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 69, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 81, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 95, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 108, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 119, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 131, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 145, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 162, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 178, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 206, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 229, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 252, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 272, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 296, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(15);
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
    'switch on a property' => [52, '$shape->type'],
    'switch on an index' => [69, "\$row['type']"],
    'if on a property' => [81, '$shape->type'],
    'if on an index' => [95, "\$row['type']"],
    'nullsafe property read' => [131, '$shape?->type'],
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
    'stacked labels sharing one body' => 108,
    'default written first' => 119,
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

    expect($file->getWarningCount())->toBe(15)
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
 * PHP_CodeSniffer tokenizes a file it cannot parse rather than refusing it, so
 * every pointer this sniff reads off the scope map can be absent. Each fixture
 * below removes one of them while satisfying every other rule it can, so the
 * missing pointer is the only thing between the file and a report.
 *
 * Five of the seven pin a specific guard, each confirmed by deleting that guard
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
 * The shipped default, read off the instance rules.xml parsed rather than off
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
 * An asymptotic fix has no observable but time, so the budget sits well above
 * the measured cost rather than near it, matching the sibling scale tests in
 * NPathComplexityTest and MappingArrayCandidateTest.
 *
 * The silence assertion is what stops the stopwatch passing vacuously: a file
 * the sniff bailed out of early would also be fast. Each level is one clause on
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

    buildRuleset([TYPE_DISCRIMINATOR_DISPATCH]);

    $started = hrtime(true);
    $file = analyzeWithSniffs([TYPE_DISCRIMINATOR_DISPATCH], $fixture);
    $elapsed = ((hrtime(true) - $started) / 1e9);

    expect($file->getWarnings())->toBe([])
        ->and($elapsed)->toBeLessThan(3.0);
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
