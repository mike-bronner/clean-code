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
 *   function is.
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
 *
 * labellessSwitch() is absent on purpose: it measures 0, and 0 is below a
 * minimum of 1, so it is reported at no threshold at all. The silence test
 * above covers it and the dedicated test below pins the 0.
 */
it('measures each counting rule exactly as PHPMD does', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredComplexities($file))->toBe([
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
    ]);
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

    expect(measuredComplexities($file))->not->toHaveKey('interfaceMethod');
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

    expect(measuredComplexities($file))->toHaveKey('labellessSwitch')
        ->and(measuredComplexities($file)['labellessSwitch'])->toBe(0);
});

/**
 * The failing fixture, pinned by line, column, and source rather than by count
 * alone, so a violation moving to a different callable would fail.
 *
 * Every callable here is built so one counting rule is load-bearing for the
 * total: drop that rule and the callable falls *below* 200 rather than merely
 * measuring differently, so the violation disappears. The fixture docblocks
 * name the rule and the number it falls to.
 */
it('flags every over-complex callable in the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        22 => [NPATH_COMPLEXITY . '.MinimumExceeded'],
        66 => [NPATH_COMPLEXITY . '.MinimumExceeded'],
        115 => [NPATH_COMPLEXITY . '.MinimumExceeded'],
        159 => [NPATH_COMPLEXITY . '.MinimumExceeded'],
        227 => [NPATH_COMPLEXITY . '.MinimumExceeded'],
    ]);
});

/**
 * The measured values behind those five violations, so the assertion above
 * cannot be satisfied by a sniff that reports the right callables for the wrong
 * reason. Each equals what a live PHPMD run reports.
 */
it('reports the same values PHPMD reports on the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(measuredComplexities($file))->toBe([
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

    expect(measuredComplexities($failing))->toHaveKey('switchLabelsMultiply')
        ->and(measuredComplexities($failing)['switchLabelsMultiply'])->toBe(200)
        ->and(measuredComplexities($passing))->toBe([]);
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

    expect(measuredComplexities($atValue))->toBe(['atOneBelowTheMinimum' => 199])
        ->and(measuredComplexities($aboveValue))->toBe([]);
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

    expect(measuredComplexities($file))->toBe(['atOneBelowTheMinimum' => 199]);
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
