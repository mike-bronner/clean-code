<?php

/**
 * Tests the custom CleanCode.Metrics.MethodNestingLevel sniff, which enforces
 * the "Indentation: Methods" clean-code standard — no more than 2 levels of
 * nesting in a method body (docs/standards/indentation-methods-max-nesting-levels.md,
 * #36).
 *
 * Every line, column and level asserted below was read off a real phpcs run over
 * the fixture rather than derived from the sniff. Two properties of the
 * diagnostic are what the assertions are for, because a "reports something"
 * test would hold without either:
 *
 * - **Where.** Each excess control structure is reported at *its own* line, not
 *   once per method at the declaration. That is the whole reason this is a
 *   custom sniff and not a configured metric, so the tuples are exact.
 * - **What.** The message carries the level actually measured, so
 *   reportedNestingLevels() reads the number back rather than trusting that a
 *   report landing at all means it landed for the right reason.
 *
 * The generic floor — registration, silence on passing.php, something reported
 * on failing.php, and the same both ways through the *installed* binary — comes
 * from the sweeps in tests/Contract/ via this sniff's entry in SWEPT_SNIFFS, so
 * it is not restated here.
 *
 * The rule is detection-only: reducing nesting means extracting a method,
 * inverting a condition or introducing a guard clause, none of which a token
 * rewriter can apply without changing behaviour. So there is no autofixed
 * fixture, and the detection-only test at the bottom pins that.
 */

declare(strict_types=1);

const METHOD_NESTING_LEVEL = 'CleanCode.Metrics.MethodNestingLevel';

const METHOD_NESTING_LEVEL_MAX_EXCEEDED = METHOD_NESTING_LEVEL . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(METHOD_NESTING_LEVEL);
});

/**
 * passing.php holds a compliant form of every construct the sniff counts, each
 * one counting rule away from being reported: a `case` label, an `elseif`/`else`
 * continuation, a `catch`/`finally`, the trailing `while` of a `do … while`, and
 * a closure and arrow function whose bodies sit at exactly 2. Counting any of
 * them one too high reddens this.
 *
 * The file also ends in top-level code nested three deep, which the sniff must
 * pass over because no function encloses it.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The per-structure reporting, pinned as exact positions. Line 56 and 57, and
 * 73 and 74, and 108 and 111, are the pairs that make this a statement about
 * per-line reporting rather than per-method: a method nested four deep reports
 * twice, at the level-3 structure and again at the level-4 one.
 *
 * Every token in register() is the deepest, self-reported construct somewhere in
 * this list, so none of them is carried by another token's report:
 * `while` at 41, `for` at 140, `switch` at 155, `do` at 171 (at the `do`, not the
 * trailing `while`), `try` at 200, `match` at 123, `if` at 91, and `T_CLOSURE` at
 * 73. Deleting any one from register() drops its own line here.
 *
 * Lines 220 and 239 cover the other role NESTING_TOKENS gives `try` and `match`
 * — a level for what their own block or arms hold, rather than a construct being
 * reported. Both were added because deleting either token from NESTING_TOKENS
 * left the whole suite green without them.
 */
it('flags each excess control structure at its own line', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 30, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 41, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 56, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 57, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 73, 'column' => 23, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 74, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 91, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 108, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 111, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 123, 'column' => 27, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 140, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 155, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 171, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 186, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 200, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 220, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 239, 'column' => 28, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The measured level, not merely that something was reported. Every level-4
 * entry below sits directly inside the level-3 one above it, so a counting
 * regression that shifted the whole file by one would keep every position in the
 * tuple assertion and be visible only here.
 */
it('reports the level it measured', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect(reportedNestingLevels($file))->toBe([
        30 => 3,
        41 => 3,
        56 => 3,
        57 => 4,
        73 => 3,
        74 => 4,
        91 => 3,
        108 => 3,
        111 => 4,
        123 => 3,
        140 => 3,
        155 => 3,
        171 => 3,
        186 => 3,
        200 => 3,
        220 => 3,
        239 => 3,
    ]);
});

/**
 * The message a developer actually reads, once, in full. The tuple and level
 * assertions above both go through structured fields; this is the only thing
 * pinning the rendered text.
 */
it('names the level found and the maximum allowed', function (): void {
    $errors = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php')->getErrors();

    expect($errors[30][17][0]['message'])
        ->toBe('Method nesting level (3) exceeds the maximum of 2; refactor to reduce nesting');
});

/**
 * The boundary pair AC #4 asks for: the same method at exactly 2 levels and
 * restructured to exactly 3. passing.php's boundaryAtTwoLevels() is silent —
 * the whole-file assertion above covers that — and failing.php's process() is
 * the same method with one loop added, reported once, at the added loop.
 *
 * Asserted here as well as in the full tuple list because the pair is the AC
 * item, and reading it out of a seventeen-entry list is not reading it.
 */
it('passes at exactly two levels and fails at exactly three', function (): void {
    $atTwo = analyzeFixture(METHOD_NESTING_LEVEL, 'passing.php');
    $atThree = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect($atTwo->getErrors())->toBe([])
        ->and(array_slice(violationTuples($atThree), 0, 1))->toBe([
            ['line' => 30, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ]);
});

/**
 * The continuation invariant, which the sniff states in two places and which
 * needs pinning in both.
 *
 * Lines 39, 55, 71 and 86 are the counting half: a structure written inside an
 * `elseif`, an `else`, a `finally` or a `do … while` is measured at the branch's
 * depth. Dropping T_ELSEIF, T_ELSE, T_FINALLY or T_DO from NESTING_TOKENS
 * measures the `while` at 2 and loses one line each.
 *
 * Lines 102, 122 and 161 are the reporting half, and are the ones nothing else
 * in the suite covers: an `if` chain and a `try` that are *themselves* over the
 * limit report once, at the opener, however many continuations follow. Adding
 * T_ELSEIF, T_ELSE, T_CATCH or T_FINALLY to register() leaves every other test
 * green and turns those three lines into eight.
 *
 * Line 145 is the near miss between the two: an `if` written inside a braced
 * `else` block is genuinely nested and is reported, so the sniff's `else`
 * lookback cannot be widened to "the previous keyword is `else`".
 */
it('counts a continuation branch as a level without reporting it twice', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'continuations.php');

    expect(violationTuples($file))->toBe([
        ['line' => 39, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 55, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 71, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 86, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 102, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 122, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 145, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 161, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and(reportedNestingLevels($file))->toBe([
        39 => 3,
        55 => 3,
        71 => 3,
        86 => 3,
        102 => 3,
        122 => 3,
        145 => 3,
        161 => 3,
    ]);
});

/**
 * Arrow functions, the form PHPCS does not put in its body tokens' `conditions`.
 *
 * Line 35 is the `fn` reported at its own position and 36 its inline `match` one
 * level deeper. Line 53 is the case the sniff had to be taught: a closure inside
 * an arrow-function body, invisible to `conditions` and therefore measured at 2
 * — silently unreported — until the arrow's recorded scope span was counted.
 * Line 82 is one arrow inside another.
 *
 * Line 98 is the one that pins where that span walk *starts*. Its conditions are
 * T_FUNCTION and T_CLOSURE, and the closure opens after the `fn`, so a walk
 * bounded at the nearest enclosing scope instead of at the method body would
 * begin inside the arrow function and measure 2. It is the only case here where
 * the bound is load-bearing.
 */
it('counts an arrow function as a nesting level for its body', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'arrow-functions.php');

    expect(violationTuples($file))->toBe([
        ['line' => 35, 'column' => 23, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 36, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 53, 'column' => 29, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 68, 'column' => 18, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 82, 'column' => 28, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 98, 'column' => 13, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and(reportedNestingLevels($file))->toBe([
        35 => 3,
        36 => 4,
        53 => 3,
        68 => 3,
        82 => 3,
        98 => 3,
    ]);
});

/**
 * The parity the test above exists to protect, stated on its own because it is
 * the actual claim and not an incidental consequence of two line numbers.
 *
 * arrow-functions.php:53 and :68 are the same nesting written two ways — a
 * closure inside an arrow-function body, and a closure inside a closure body.
 * Both are anonymous functions under the standard and both read as three levels,
 * so both must measure 3. Read off `conditions` alone the first measures 2: an
 * over-nested arrow function body is exactly the thing a nesting linter must not
 * pass over in silence.
 */
it('measures a closure the same inside an arrow body as inside a closure body', function (): void {
    $levels = reportedNestingLevels(analyzeFixture(METHOD_NESTING_LEVEL, 'arrow-functions.php'));

    expect($levels[53])->toBe($levels[68]);
});

/**
 * AC #6 — the existing sniffs, evaluated against these fixtures rather than
 * against their documentation, and re-run on every suite run so that a vendor
 * upgrade which started covering this standard would redden the claim rather
 * than leaving a stale paragraph in the docs.
 *
 * `Generic.Metrics.NestingLevel` says nothing at all about failing.php. It
 * measures maximum brace depth per function and warns above 5 / errors above 10;
 * the deepest method here measures 4, so at its shipped thresholds it does not
 * enforce a 2-level limit in any configuration a consumer would receive. The
 * assertion is paired with a count of what the rest of the Generic standard
 * reported on the same run, so an empty result cannot come from a standard that
 * never loaded.
 */
it('is not already covered by Generic.Metrics.NestingLevel', function (): void {
    $file = analyzeWithStandard('Generic', fixturePath('MethodNestingLevelSniff', 'failing.php'));
    $sources = array_column(violationTuples($file), 'source');

    expect(array_filter($sources, static fn (string $source): bool => str_starts_with(
        $source,
        'Generic.Metrics.NestingLevel'
    )))->toBe([])->and($sources)->not->toBeEmpty();
});

/**
 * The other sniff AC #6 names. `SlevomatCodingStandard.Complexity.Cognitive`
 * does fire on this fixture — 11 times — which is why the evaluation cannot stop
 * at "it reports nothing". It reports something else:
 *
 * - once per *declaration*, at the declaration line, never at the offending
 *   structure. Its 11 lines and this sniff's 15 have no line in common, which is
 *   what the assertion states;
 * - and it is a score, not a limit: it misses matchInsideNestedLoops() (a real
 *   3-level violation) entirely while reporting 10 for two methods that nest to
 *   different depths. No ceiling on that number expresses "no deeper than 2".
 */
it('is not already covered by SlevomatCodingStandard.Complexity.Cognitive', function (): void {
    $cognitive = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath('MethodNestingLevelSniff', 'failing.php')
    );
    $cognitiveLines = array_column(array_filter(
        violationTuples($cognitive),
        static fn (array $violation): bool => str_starts_with(
            $violation['source'],
            'SlevomatCodingStandard.Complexity.Cognitive'
        )
    ), 'line');

    $ours = array_column(violationTuples(analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php')), 'line');

    expect($cognitiveLines)->toBe([26, 37, 52, 69, 86, 104, 136, 151, 167, 182, 196])
        ->and(array_intersect($cognitiveLines, $ours))->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
