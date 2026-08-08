<?php

/**
 * Behaviour of CleanCode.ControlStructures.DisallowCountInLoopExpression, the
 * custom sniff that replicates PHPMD's Design/CountInLoopExpression rule
 * (issue #99). Fixtures live in tests/fixtures/DisallowCountInLoopExpressionSniff/.
 *
 * No existing PHPCS, Slevomat, or Generic sniff matches the rule, which is why
 * this is a custom sniff rather than a rules.xml wiring — the two candidates and
 * the reasons they were rejected are set out in
 * docs/phpmd/design-countinloopexpression.md.
 *
 * There is no autofixed.php because the rule is detection-only, matching PHPMD.
 * Hoisting count() out of a loop condition is not a safe mechanical rewrite: a
 * loop that mutates the array as it iterates depends on the re-evaluation, so
 * the fix has to be made by whoever knows the loop's intent. The fixable-count
 * test below pins that, so the absent autofix fixture stays an asserted fact
 * rather than an assumption.
 *
 * Where this sniff deliberately diverges from PHPMD 2.15.0 — stricter on
 * uppercase COUNT() and the fully-qualified \count(), looser on the first-class
 * callable count(...) — each divergence gets its own test below, so that the
 * decision stays on record rather than becoming a regression waiting to be
 * quietly reverted.
 */

declare(strict_types=1);

const COUNT_IN_LOOP_SNIFF = 'CleanCode.ControlStructures.DisallowCountInLoopExpression';

/**
 * Every violation in failing.php, in file order, paired with the loop shape it
 * pins. Written out as exact line/column tuples rather than a count so that a
 * violation moving between shapes cannot pass unnoticed.
 */
const COUNT_IN_LOOP_VIOLATIONS = [
    [4, 19],   // for: the shape from PHPMD's own documentation
    [9, 19],   // for: sizeof(), the alias PHPMD names alongside count()
    [14, 8],   // while: count() is the whole condition
    [18, 8],   // while: sizeof()
    [26, 10],  // do-while: reported at the trailing while
    [29, 8],   // while: count() as one arm of a compound condition
    [33, 31],  // for: count() as the second arm of a compound condition
    [38, 8],   // while: first of two calls in one condition
    [38, 28],  // while: second of two calls in the same condition
    [44, 19],  // for: outer loop of a nest
    [45, 23],  // for: inner loop of the same nest
    [51, 8],   // while: alternative (endwhile) syntax
    [56, 19],  // for: uppercase COUNT() — stricter than PHPMD
    [61, 20],  // for: fully-qualified \count() — stricter than PHPMD
    [65, 9],   // while: fully-qualified \sizeof() — stricter than PHPMD
    [70, 15],  // while: nested inside another call in the condition
    [74, 60],  // while: nested inside an arrow function in the condition
    [81, 55],  // for: arrow function in the initialiser, count() in the condition
    [89, 9],   // for: closure body in the initialiser, count() in the condition
    [96, 8],   // while: count(...$rows) — a spread argument is still a real call
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COUNT_IN_LOOP_SNIFF);
});

/**
 * passing.php is the contract sweep's floor fixture and carries the compliant
 * rewrite the rule asks for: the size taken once before the loop. It is
 * discriminating rather than merely empty because it also puts count() and
 * sizeof() in the *bodies* of a for, a while, a do-while, and a foreach — calls
 * that are re-evaluated every iteration but are not the loop's condition, so a
 * sniff that swept the whole loop instead of its header would report them.
 *
 * The near-miss families each have their own fixture, so that the tests below
 * pin one mechanism apiece instead of four tests all asserting this one file is
 * empty.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every count and sizeof call in a loop condition at its exact position', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => COUNT_IN_LOOP_SNIFF . '.Found',
        ],
        COUNT_IN_LOOP_VIOLATIONS
    );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The message names the call as it was written, so an uppercase COUNT() or a
 * sizeof() is quoted back the way the author typed it rather than normalised.
 */
it('names the offending function in the message as it was written', function (): void {
    $errors = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors();

    $messageAt = static function (int $line, int $column) use ($errors): string {
        return $errors[$line][$column][0]['message'];
    };

    expect($messageAt(4, 19))->toStartWith('count() must not be called in a loop condition')
        ->and($messageAt(9, 19))->toStartWith('sizeof() must not be called in a loop condition')
        ->and($messageAt(56, 19))->toStartWith('COUNT() must not be called in a loop condition');
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php');

    // Guard against a vacuous pass: an empty report also has zero fixable
    // violations, so pin that the violations are actually there first.
    expect($file->getErrorCount())->toBe(count(COUNT_IN_LOOP_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(COUNT_IN_LOOP_VIOLATIONS), false));
});

/**
 * A `for` header has three sections and only the middle one is the loop's
 * continuation test. count() in the initialiser runs once, and count() in the
 * increment is not the test — PHPMD 2.15.0 reports neither, and neither does
 * this sniff.
 *
 * for-sections.php holds nothing else: every call in it is in the initialiser
 * or the increment, and every condition is a plain variable comparison. Drop
 * the section restriction and all six report. passing.php cannot stand in for
 * it — passing.php has no count() in a for header at all, so it survives that
 * mutation untouched.
 */
it('ignores count in a for loop initialiser or increment', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'for-sections.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The section counter advances on depth-zero semicolons only. A closure body or
 * an argument list inside the initialiser carries its own semicolons, and a
 * naive scan (the one Squiz.PHP.DisallowSizeFunctionsInLoops uses — findNext
 * for the first semicolon, findPrevious for the last) treats them as section
 * separators. That shifts the condition boundary backwards onto the initialiser
 * and reports the count() calls sitting there.
 *
 * This is a distinct mechanism from the section restriction above, and
 * nested-separators.php isolates it: in every loop the count() sits in the
 * initialiser *after* a nested semicolon, so only a depth-blind scan can reach
 * it. for-sections.php survives the depth mutation (its initialisers carry no
 * nested semicolons), and this fixture survives nothing else.
 */
it('does not mistake a semicolon nested in the for initialiser for a section separator', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'nested-separators.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The mirror image of the test above, and the reason the section counter tracks
 * bracket depth rather than jumping over nested bodies via `scope_closer`.
 * PHPCS gives an arrow function a `scope_closer` despite it having no braces,
 * and for `fn (int $n): int => $n;` in a for header that pointer lands on the
 * header's *own* first separator. A scan that jumps to it consumes a real
 * separator, never reaches the condition section, and silently reports nothing
 * for the loop — the failure mode found in PR #244 on a sibling sniff.
 */
it('still finds the condition when an arrow function precedes it in the for header', function (): void {
    $sources = violationSourcesByLine(
        analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
    );

    expect($sources)->toHaveKey(81)
        ->and($sources[81])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

/**
 * A same-named method or static method is a different function that happens to
 * share the short name, as is any qualified name resolving outside the global
 * namespace, a method *declaration* of that name, and a class of that name being
 * instantiated. PHPMD 2.15.0 reports none of them, and neither does this sniff.
 *
 * Every spelling in same-named-callables.php sits inside a real loop condition,
 * because that is the only place the sniff looks: process() scans between a
 * registered T_FOR/T_WHILE's parenthesis_opener and parenthesis_closer, so a
 * declaration placed *beside* the loops is never visited and would pin nothing.
 * The two declaration spellings therefore reach the condition the only way they
 * can — through an anonymous class expression, `(new class { … })->count()` —
 * and the class-name spelling through `(new count($items))->hasMore()`.
 *
 * That is what makes the file discriminating: the only thing keeping it silent
 * is the callable check, and each member of NON_FUNCTION_CALL_PRECEDERS has a
 * shape here that reaches it. Removing any one of T_OBJECT_OPERATOR,
 * T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, or T_NEW reddens this
 * test; all five were confirmed one at a time. The other compliant fixtures
 * survive those mutations, because none of them spells the name in a condition.
 */
it('leaves same-named methods, static calls, and qualified names alone', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'same-named-callables.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A deliberate divergence from PHPMD 2.15.0, and the only one in the *looser*
 * direction. PHP 8.1's first-class callable syntax names the function without
 * invoking it: `count(...)` evaluates to a Closure, so no array is counted, and
 * there is no re-count per iteration and no mutating array to move the loop's
 * termination point — none of the harm the rule exists to prevent.
 *
 * PHPMD reports it anyway: its rule matches the AST's FunctionPostfix node
 * without inspecting the argument list. Verified against a live PHPMD 2.15.0 —
 * every shape in this fixture is flagged by PHPMD and silent here. That is a
 * false positive on in-language syntax (composer.json requires php ^8.1), so it
 * is not replicated; the divergence is recorded in
 * docs/phpmd/design-countinloopexpression.md.
 *
 * first-class-callable.php isolates the check: the bare form, both function
 * names, the form nested inside another call, the form in a `for` header's
 * condition section, and the form with a comment between the name and the
 * ellipsis. Dropping the first-class-callable check reports all five loops.
 *
 * The exemption is narrow, and failing.php line 96 is the other side of it: a
 * real spread call, `count(...$rows)`, re-counts on every pass like any other
 * call and stays reported. Narrowing the check to the ellipsis alone — without
 * requiring the closing parenthesis right after it — makes that line go silent,
 * so the two fixtures pin the boundary from both directions.
 */
it('ignores the first-class callable syntax, which never invokes the function', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'first-class-callable.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still flags a real call that spreads its arguments', function (): void {
    $sources = violationSourcesByLine(
        analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
    );

    expect($sources)->toHaveKey(96)
        ->and($sources[96])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

/**
 * Two deliberate divergences from PHPMD 2.15.0, both in the stricter direction,
 * both true defects. PHPMD matches the callable's name literally, so it misses
 * `COUNT($items)` (PHP function names are case-insensitive, so it is the same
 * call) and `\count($items)` (an explicit reference to the same global
 * function). Verified against a live PHPMD 2.15.0 run: neither line is reported
 * by `phpmd … design`. Both are kept, and documented in
 * docs/phpmd/design-countinloopexpression.md.
 */
it('flags the case and namespace spellings PHPMD misses', function (): void {
    $sources = violationSourcesByLine(
        analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
    );

    expect($sources[56])->toBe([COUNT_IN_LOOP_SNIFF . '.Found'])
        ->and($sources[61])->toBe([COUNT_IN_LOOP_SNIFF . '.Found'])
        ->and($sources[65])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

/**
 * The parenthesis guard, which is genuinely reachable rather than defensive
 * boilerplate. In truncated source PHPCS leaves a loop keyword's bounds
 * incomplete two ways, and the paired isset() covers both:
 *
 *   - `while (count($items) > 0` with no closing parenthesis gets a
 *     parenthesis_opener but no parenthesis_closer. That is what
 *     unclosed-condition.php pins: the source has no loop condition to judge,
 *     but it does carry a count() call the sniff would reach if it defaulted
 *     the missing end. Verified by mutation: of the three ways to weaken the
 *     guard, only *defaulting both bounds* (`?? count($tokens)` for the closer,
 *     `?? $stackPtr` for the opener) makes this fixture report, and that is the
 *     mutation this test catches.
 *
 *     Dropping the closer half, or dropping the guard outright, leaves the
 *     fixture silent — the absent key resolves to null and `$i < null` is false
 *     on the first evaluation, so the scan never runs and the guard is never
 *     what stopped it. The paired isset() is still worth keeping: it is what
 *     makes the refusal deliberate rather than an accident of loose comparison,
 *     and it avoids the undefined-index warning the bare read would emit. But
 *     it is a stronger guard than this fixture alone can prove, and saying
 *     otherwise would overstate what the mutation run showed.
 *   - A bare `while` keyword gets neither bound. This one has no fixture, and
 *     deliberately so: it is a no-op the same guard already covers, and it
 *     cannot be made to assert anything. Appending any call after the bare
 *     keyword makes the tokeniser adopt *that* call's parentheses as the
 *     loop's condition, so the token no longer has the missing bounds the
 *     fixture would exist to exercise. Left unfixtured rather than shipped as
 *     a file that passes forever without testing anything.
 */
it('refuses a malformed loop header rather than guessing at it', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'unclosed-condition.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
