<?php

/**
 * Tests the custom CleanCode.Metrics.NPathComplexity sniff, which replicates
 * PHPMD's CodeSize/NPathComplexity rule
 * (docs/phpmd/codesize-npathcomplexity.md).
 *
 * Every expectation here was calibrated against a live PHPMD 2.15.0 / PDepend
 * run over the same fixtures rather than against PHPMD's documentation, which
 * states none of the counting rules. Four are worth stating outright, because
 * they are the ones a reader is most likely to assume the other way round:
 *
 * - NPath **multiplies** where cyclomatic complexity adds. Two independent
 *   `if`s are 4 paths, not 3 decision points.
 * - PHPMD reports a callable whose NPath is **at or above** the configured
 *   minimum (`$npath < $threshold` is the only early return in PHPMD's own
 *   Rule\Design\NpathComplexity), not strictly above it. So 200 is a violation
 *   at the default minimum of 200, and 199 is not.
 * - `xor` **is** counted, unlike in cyclomatic complexity, where PDepend
 *   ignores it — CleanCode.Metrics.ExcessiveClassComplexity excludes it for
 *   exactly that reason, and the two sniffs disagreeing here is deliberate.
 * - a closure or arrow function is **not** its own artifact; a nested named
 *   function is. Its body is folded into the enclosing callable only where
 *   PDepend walks statements, though — in expression position the control flow
 *   inside it is worth nothing, which the closure pair below pins.
 *
 * The rule is detection-only — the fix is to break the callable up — so there
 * is no autofixed fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const NPATH_COMPLEXITY = 'CleanCode.Metrics.NPathComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NPATH_COMPLEXITY);
});

/**
 * passing.php carries the near miss the threshold has to stay silent on —
 * atOneBelowTheMinimum() measures exactly 199 against a minimum of 200 — next
 * to uncountedConstructs(), which holds the constructs PDepend does not score,
 * and the bodiless, anonymous-class, and closure shapes. Each smaller callable
 * after them pins one counting rule the near miss never reaches; the
 * measurement test below names them one by one.
 *
 * Silence alone is a weak verdict here: nothing in this file is anywhere near
 * 200 except the near miss, so a counting rule could break badly and this
 * assertion would not notice. The measurement test below closes that gap by
 * pinning each callable's exact value; this one pins that the default
 * threshold reports nothing.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The counting rules themselves, pinned as exact numbers rather than as a
 * verdict against a threshold. Dropping the minimum to 1 makes the sniff report
 * every callable it measures, so the message carries each measurement out where
 * a test can read it — which is the only way any single counting rule becomes
 * individually observable.
 *
 * Every number below is what a live PHPMD 2.15.0 run reports for the same
 * callable. Each line is one rule:
 *
 * - 199 — the near miss, and the multiplicative sequence that produces it.
 * - 2 on uncountedConstructs() — `match`, `??`, `??=`, `?->`, `!`, `goto`, and
 *   a first-class callable are all worth nothing, and so are the boolean
 *   operators written inside a string, a comment, and a heredoc. Only the one
 *   `if` scores.
 * - 1 on abstractMethod() — a bodiless method is measured and scores 1.
 * - 1 on anonymousClassBody() — the anonymous class body is a separate
 *   artifact, so the two `if`s inside it are not folded into the enclosing
 *   function, and the `;` inside it does not truncate the `return` statement.
 * - 2 on inlineClosures() — an arrow function's ternary belongs to the
 *   enclosing callable.
 * - 2 on doWhileLoop() — the `while` of a `do … while` is counted once, not
 *   twice.
 * - 3 on tryBlocksSum() — try, catch, and finally are summed, and the construct
 *   itself adds nothing.
 * - 3 on elseIfChain() — an `elseif` chain nests rather than summing flat.
 * - 4 on alternativeSyntax() — the alternative syntax measures as the braced
 *   form does.
 * - 2 on nestedNamedFunction() and 2 on nestedInner() — a nested *named*
 *   function is its own artifact, measured separately and excluded from the
 *   callable around it.
 * - 2 on forIgnoresInitAndUpdateClauses() against 4 on
 *   forCountsItsConditionClause() — a `for` reads only its middle clause. The
 *   two carry the same two boolean operators in different clauses, so reading
 *   all three clauses would score both 4 and reading none would score both 2.
 * - 3 on standaloneWhileLoop() — a `while` of its own, which doWhileLoop() never
 *   reaches because the `while` of a `do … while` is consumed with the loop.
 * - 4 on shortTernaryDoublesItsCondition() — the `?:` formula doubles the
 *   condition where the full form would score the same expression 3.
 * - 5 on logicalKeywordOperators() — `||`, `and`, and `or` each score 1, the
 *   three boolean tokens no other callable counts live.
 * - 3 on throwYieldAndContinue() — `throw`, `yield`, and `continue` score
 *   nothing, so only the `foreach` around them counts.
 * - 2 on matchArmTernaryMultiplies() against 1 on matchArmBooleanIsUncounted() —
 *   a `match` adds nothing itself, but an arm body is still an ordinary
 *   expression, so a ternary in one multiplies while a boolean operator in one
 *   does not. uncountedConstructs() cannot show this: its arms hold nothing
 *   scoreable, so it cannot separate "skipped" from "empty".
 * - 5 on ternaryConditionStopsAtTheFirstOperator() against 6 on
 *   ternaryConditionIncludesAParenthesisedGroup() — a ternary's condition is the
 *   first *child node* of the expression holding it, not everything left of the
 *   `?`. The two differ by one pair of parentheses, which is what moves the
 *   `&&` into the condition and gets it counted twice.
 * - 4 on returnTernaryCountsItsConditionTwice() against 3 on
 *   assignedTernaryCountsItsConditionOnce() — the `return` quirk, and the
 *   assignment that isolates it.
 * - 8 on closureInStatementPositionIsWalked() against 1 on
 *   closureInExpressionPositionIsNotWalked() — the same closure body, moved
 *   from an assignment into a `return`. PDepend reaches the `if`s inside a
 *   closure only while walking statements; `sumComplexity()`, which scores an
 *   expression, sums boolean operators and ternaries and never runs the
 *   statement visitor. Descending into the closure body from the expression
 *   walk would score the second 8 too and break parity.
 * - 4 on matchArmBooleanCountsInACondition() and 2 on
 *   matchArmBooleanCountsInAReturn(), against 1 on
 *   matchArmBooleanIsUncounted() — the same boundary from the other side. An
 *   arm's boolean operator is uncounted at statement level and counted once the
 *   `match` sits in an expression PDepend sums.
 * - 3 on switchWithAMatchSubject(), 2 on switchWithAMatchSubjectAndNoBoolean(),
 *   and 3 on alternativeSyntaxSwitchWithAMatchSubject() — the one shape PHPCS
 *   3.13.6 builds no scope for. Read from the tokenizer alone all three look
 *   label-less, which scores 0; the boolean-free twin is the discriminating
 *   one, because 0 is reported at no threshold and the callable disappears from
 *   this map rather than reading low.
 *
 * labellessSwitch() is absent on purpose: it measures 0, and 0 is below a
 * minimum of 1, so it is reported at no threshold at all. The silence test
 * above covers it and the dedicated test below pins the 0.
 */
it('measures each counting rule exactly as PHPMD does', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->toBe([
        'atOneBelowTheMinimum' => 199,
        'uncountedConstructs' => 2,
        'abstractMethod' => 1,
        'anonymousClassBody' => 1,
        'inlineClosures' => 2,
        'doWhileLoop' => 2,
        'tryBlocksSum' => 3,
        'elseIfChain' => 3,
        'alternativeSyntax' => 4,
        'nestedNamedFunction' => 2,
        'nestedInner' => 2,
        'forIgnoresInitAndUpdateClauses' => 2,
        'forCountsItsConditionClause' => 4,
        'standaloneWhileLoop' => 3,
        'shortTernaryDoublesItsCondition' => 4,
        'logicalKeywordOperators' => 5,
        'throwYieldAndContinue' => 3,
        'matchArmTernaryMultiplies' => 2,
        'matchArmBooleanIsUncounted' => 1,
        'ternaryConditionStopsAtTheFirstOperator' => 5,
        'ternaryConditionIncludesAParenthesisedGroup' => 6,
        'returnTernaryCountsItsConditionTwice' => 4,
        'assignedTernaryCountsItsConditionOnce' => 3,
        'closureInStatementPositionIsWalked' => 8,
        'closureInExpressionPositionIsNotWalked' => 1,
        'matchArmBooleanCountsInACondition' => 4,
        'matchArmBooleanCountsInAReturn' => 2,
        'switchWithAMatchSubject' => 3,
        'switchWithAMatchSubjectAndNoBoolean' => 2,
        'alternativeSyntaxSwitchWithAMatchSubject' => 3,
        'bracelessDoWrappingALoop' => 4,
        'bracelessIfWrappingALoop' => 3,
        'bracelessWhileWrappingAnIf' => 3,
        'bracelessForWrappingAnIf' => 3,
        'bracelessForeachWrappingAnIf' => 3,
        'bracelessElseWrappingALoop' => 3,
        'bracelessIfWrappingABracelessIf' => 3,
        'nestedSwitchInACaseBody' => 3,
    ]);
});

/**
 * A braceless single-statement body carries its own NPath when the statement is
 * itself a compound construct.
 *
 * The measurement test above already holds every value; this one states the
 * rule the seven callables share, and pins it per construct so a fix that
 * closed only the construct a bug was first reported against cannot pass. Each
 * of `do`, `if`, `while`, `for`, `foreach` and `else` can own a braceless body,
 * and each reaches it through the same dispatch — so each is asserted, rather
 * than one being taken as evidence for the rest. The last is a braceless body
 * that is itself braceless, which is the recursive case.
 *
 * Every value is what a live PHPMD 2.15.0 run reports for the same callable.
 */
it('measures a braceless body of every construct that can own one', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    $measured = measuredNPathComplexities($file);

    expect($measured)
        ->toHaveKeys([
            'bracelessDoWrappingALoop',
            'bracelessIfWrappingALoop',
            'bracelessWhileWrappingAnIf',
            'bracelessForWrappingAnIf',
            'bracelessForeachWrappingAnIf',
            'bracelessElseWrappingALoop',
            'bracelessIfWrappingABracelessIf',
        ])
        ->and($measured['bracelessDoWrappingALoop'])->toBe(4)
        ->and($measured['bracelessIfWrappingALoop'])->toBe(3)
        ->and($measured['bracelessWhileWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessForWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessForeachWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessElseWrappingALoop'])->toBe(3)
        ->and($measured['bracelessIfWrappingABracelessIf'])->toBe(3);
});

/**
 * A scope-less `switch` inside another `switch`'s case body — the nesting no
 * other callable in the fixture carries. The inner labels belong to the inner
 * construct, so reading them as ranges of the outer one would measure high.
 *
 * A live PHPMD 2.15.0 run reports 3.
 */
it('keeps a nested switch\'s labels out of the enclosing switch', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->toHaveKey('nestedSwitchInACaseBody')
        ->and(measuredNPathComplexities($file)['nestedSwitchInACaseBody'])->toBe(3);
});

/**
 * A method declared in an *interface* is measured by neither tool at any
 * threshold, while an abstract method in a class is measured and scores 1.
 * PHPMD's method rules walk the methods of classes and traits, not of
 * interfaces.
 *
 * The measurement test above already shows abstractMethod() reported at a
 * minimum of 1. This one is the other half: interfaceMethod() must be absent
 * from that same run, which is what separates "not measured" from "measured
 * low".
 */
it('never reports a method declared in an interface', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->not->toHaveKey('interfaceMethod');
});

/**
 * A `switch` scores its expression plus one range per label, with nothing added
 * for the construct and nothing for a missing `default`. A `switch` carrying no
 * labels therefore scores 0, and 0 multiplied into the sequence zeroes the
 * whole callable — labellessSwitch() holds an `if` worth 2 and still measures
 * 0. PDepend does exactly this.
 *
 * A minimum of 0 is what makes the measurement observable: at 1 the callable is
 * below the threshold and reports nothing, which is indistinguishable from a
 * sniff that skipped it.
 */
it('scores a switch with no labels as zero, matching PDepend', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 0;
    });

    expect(measuredNPathComplexities($file))->toHaveKey('labellessSwitch')
        ->and(measuredNPathComplexities($file)['labellessSwitch'])->toBe(0);
});

/**
 * A `switch` whose subject holds a `match` still finds its labels, even though
 * PHPCS 3.13.6 attaches no scope to it and leaves it out of its own labels'
 * `conditions`.
 *
 * Taking the tokenizer at its word reads the switch as label-less, and a
 * label-less switch scores 0 — which multiplies the whole callable to 0 and
 * hides it at every threshold, exactly as labellessSwitch() shows. So the
 * failure this guards against is silence, not a wrong number, and a minimum of
 * 1 is what makes it observable: a callable scoring 0 is below it and absent
 * either way, so each of the three must both be present *and* carry its
 * PHPMD value.
 *
 * The boolean-free twin carries the assertion's weight. The other two hold a
 * `&&` in an arm, which the subject scores whether or not the labels are found,
 * so only the twin falls to 0 on its own.
 */
it('finds the labels of a switch the tokenizer built no scope for', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))
        ->toHaveKeys([
            'switchWithAMatchSubject',
            'switchWithAMatchSubjectAndNoBoolean',
            'alternativeSyntaxSwitchWithAMatchSubject',
        ])
        ->and(measuredNPathComplexities($file)['switchWithAMatchSubject'])->toBe(3)
        ->and(measuredNPathComplexities($file)['switchWithAMatchSubjectAndNoBoolean'])->toBe(2)
        ->and(measuredNPathComplexities($file)['alternativeSyntaxSwitchWithAMatchSubject'])->toBe(3);
});

/**
 * The failing fixture, pinned by line, column, and source rather than by count
 * alone, so a violation moving to a different callable would fail.
 *
 * The column is part of the assertion because the report is attached to the
 * `function` keyword rather than to the line: the four top-level functions sit
 * at column 1 and the method sits at column 12, so a regression that reattached
 * the report to the callable's name, its visibility modifier, or the line's
 * first token would move at least one of them while leaving every line
 * unchanged.
 *
 * Every callable here is built so one counting rule is load-bearing for the
 * total: drop that rule and the callable falls *below* 200 rather than merely
 * measuring differently, so the violation disappears. The fixture docblocks
 * name the rule and the number it falls to.
 */
it('flags every over-complex callable in the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 66, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 115, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 159, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 227, 'column' => 12, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The measured values behind those five violations, so the assertion above
 * cannot be satisfied by a sniff that reports the right callables for the wrong
 * reason. Each equals what a live PHPMD run reports.
 */
it('reports the same values PHPMD reports on the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(measuredNPathComplexities($file))->toBe([
        'multipliesSequentialBranches' => 256,
        'switchLabelsMultiply' => 200,
        'keywordXorAndReturnChain' => 204,
        'closureBodiesBelongToTheEnclosingCallable' => 207,
        'elseIfWithSpace' => 204,
    ]);
});

/**
 * The comparison itself, which is the one thing a threshold rule can get wrong
 * without any counting rule being wrong.
 *
 * switchLabelsMultiply() measures exactly 200 and atOneBelowTheMinimum()
 * exactly 199, so the pair straddles the default minimum by one. Reading
 * "minimum" as a tolerated value — reporting only above 200 — would silence the
 * first; reporting from 199 would flag the second. Both fixtures are checked at
 * the default rather than at a configured value, because the default is what
 * consumers of rules.xml get.
 */
it('reports at the minimum and stays silent one below it', function (): void {
    $failing = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');
    $passing = analyzeFixture(NPATH_COMPLEXITY, 'passing.php');

    expect(measuredNPathComplexities($failing))->toHaveKey('switchLabelsMultiply')
        ->and(measuredNPathComplexities($failing)['switchLabelsMultiply'])->toBe(200)
        ->and(measuredNPathComplexities($passing))->toBe([]);
});

/**
 * The same boundary again, driven from the other side: holding the fixture
 * still and moving the threshold across the value a callable measures.
 *
 * atOneBelowTheMinimum() measures 199, so a minimum of 199 must report it and a
 * minimum of 200 must not. Together with the test above this pins the
 * comparison at both `>=` and `<`, which no single threshold can do alone.
 */
it('treats the minimum as inclusive at any configured value', function (): void {
    $atValue = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 199;
    });
    $aboveValue = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 200;
    });

    expect(measuredNPathComplexities($atValue))->toBe(['atOneBelowTheMinimum' => 199])
        ->and(measuredNPathComplexities($aboveValue))->toBe([]);
});

/**
 * The threshold has to arrive the way a consuming ruleset delivers it — as the
 * raw string PHPCS reads out of a `<property>` element — not only as an already
 * typed integer. That is why the sniff's property is untyped and cast where it
 * is read: an `int` declaration would turn `<property name="minimum"
 * value="199"/>` into a TypeError when a consumer configured it.
 */
it('accepts the minimum from a ruleset property', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        NPATH_COMPLEXITY,
        'passing.php',
        ['minimum' => '199']
    );

    expect(measuredNPathComplexities($file))->toBe(['atOneBelowTheMinimum' => 199]);
});

/**
 * The rule is report-only, matching PHPMD, which offers no fix for it either.
 * Breaking a callable apart is a design change with no mechanical rewrite, so
 * there is no autofixed fixture and no violation may advertise itself as
 * fixable.
 */
it('reports without offering a fix', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(violationFixableFlags($file))->not->toContain(true);
});

/**
 * The message calls a class member a "method" and a free function a "function",
 * the way PHPMD's own message does. measuredNPathComplexities() reads the two through
 * a non-capturing alternation and so cannot tell them apart, which is why the
 * whole message is asserted here instead — the sibling
 * ExcessiveMethodLengthTest does the same for the same reason.
 *
 * failing.php holds only free functions, so the method half comes from
 * passing.php's abstract method at a minimum of 1.
 */
it('names the callable kind the way PHPMD does', function (): void {
    $functions = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');
    $methods = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(violationMessages($functions))->toContain(
        'The function multipliesSequentialBranches() has an NPath complexity of 256, at or '
            . 'above the configured minimum of 200; break it into smaller pieces (see '
            . 'docs/phpmd/codesize-npathcomplexity.md)'
    )->and(violationMessages($methods))->toContain(
        'The method abstractMethod() has an NPath complexity of 1, at or above the configured '
            . 'minimum of 1; break it into smaller pieces (see '
            . 'docs/phpmd/codesize-npathcomplexity.md)'
    );
});

/**
 * NPath multiplies, so a flat run of independent `if`s doubles the measurement
 * per branch and passes PHP_INT_MAX at 63 of them — a few kilobytes of
 * unremarkable code, well within what a generator or a long-lived legacy method
 * produces without anyone trying.
 *
 * Before the arithmetic saturated, that was not a large number in a report but a
 * crash: the product silently became a `float`, and returning a `float` from an
 * `int`-typed method under `declare(strict_types=1)` raises a TypeError, which
 * PHP_CodeSniffer does not catch — Runner catches `\Exception`, and TypeError is
 * an `\Error`. One such callable therefore aborted the entire run, taking every
 * other file and every other sniff with it.
 *
 * 70 branches is comfortably past the boundary. The assertion is deliberately in
 * two parts: reaching the expectation at all proves no TypeError escaped, and
 * the message proves the measurement stopped at the ceiling and *says* it is a
 * lower bound rather than reporting the ceiling as an exact count.
 */
it('saturates instead of overflowing on an astronomically branching callable', function (): void {
    $fixture = stageGeneratedFixture('overflow.php', sequentialBranchFixture(70));

    $file = analyzeWithSniffs([NPATH_COMPLEXITY], $fixture);

    expect(violationMessages($file))->toBe([
        'The function manyBranches() has an NPath complexity of at least ' . PHP_INT_MAX
            . ', at or above the configured minimum of 200; break it into smaller pieces '
            . '(see docs/phpmd/codesize-npathcomplexity.md)',
    ]);
});

/**
 * A chain of short ternaries — `$a ?: $a ?: $a …` — is left-associative, needs no
 * parentheses, and is valid PHP that any linter will happily be handed. Finding
 * where one link's else-branch ends used to mean scanning to the end of the
 * whole statement, at every link, which is O(n) work n times over.
 *
 * Measured in this harness at 4800 links: 0.34s per phpcs run before the scan
 * memoised its result and 0.30s after is *not* the interesting comparison — the
 * growth is. At 800 / 1600 / 3200 links the unmemoised walk ran 0.22s / 0.65s /
 * 2.39s, the ~3.7x per doubling that names it quadratic, while the memoised one
 * ran 0.29s / 0.30s / 0.34s and stayed flat. A file with a few thousand links
 * therefore stalled a run for a meaningful multiple of a CI budget, and nothing
 * in this tree bounded it.
 *
 * An asymptotic fix has no observable but time, so the budget sits well above
 * the measured cost rather than near it, exactly as the sibling nesting-scale
 * test in MappingArrayCandidateTest does.
 *
 * The measurement assertion is what stops the stopwatch from passing vacuously:
 * a file the sniff silently gave up on would also be fast. It is load-bearing in
 * a second way. A chain nests to the right, so each link adds 2 and the whole
 * chain is 2n — 9600 here, confirmed against a live PHPMD run at 100 links,
 * which reports 200. Truncating the scan at the next `?` instead of memoising
 * its result would also make the walk linear, but it would read each link as its
 * own statement and *multiply* them into 2 ** 4800. This number is what holds
 * the fix to one that keeps the measurement PHPMD's.
 */
it('stays linear on a long chain of ternaries', function (): void {
    $fixture = stageGeneratedFixture('ternaries.php', ternaryChainFixture(4800));

    buildRuleset([NPATH_COMPLEXITY]);

    $started = hrtime(true);
    $file = analyzeWithSniffs([NPATH_COMPLEXITY], $fixture);
    $elapsed = ((hrtime(true) - $started) / 1e9);

    expect(measuredNPathComplexities($file))->toBe(['chainedTernaries' => 9600])
        ->and($elapsed)->toBeLessThan(2.0);
});
