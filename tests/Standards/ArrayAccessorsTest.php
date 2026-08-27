<?php

/**
 * Tests the custom CleanCode.Arrays.ArrayAccessors sniff (Arrays: Array
 * Accessors, #33). Fixtures live in tests/fixtures/ArrayAccessorsSniff/:
 * compliant data_get() usage in passing.php, the constructs the standard
 * deliberately leaves alone in boundaries.php, and the flagged reads in
 * failing.php. Three fixtures cover input PHP itself would reject, which the
 * sniff must handle rather than fall over on: unterminated.php for a bracket,
 * brace, or operator that never completes, trailing-variable.php for a file
 * ending mid-statement on a bare variable, and malformed.php for a statement
 * that parses to nonsense. tokenizer-limits.php records a PHP_CodeSniffer
 * defect the sniff cannot see past. The rule is detection-only, so there is no
 * autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const ARRAY_ACCESSORS = 'CleanCode.Arrays.ArrayAccessors';

/**
 * The DirectArrayAccess diagnostic with the chain root left open, so an
 * assertion can name the variable a violation is *about* rather than only the
 * line and column it lands on. Which root a shared line reports on is the whole
 * question in the nested-`foreach` cases, where a read and a write target can
 * sit on one line and a count alone cannot tell them apart.
 */
const ARRAY_ACCESSORS_READ = 'Direct array element access on %1$s is not allowed; use data_get(%1$s, ...) '
    . 'so a missing element falls back instead of erroring';

/**
 * A staggered/staircase file of $size reads, each one construct deeper than the
 * read before it, all sharing one right-nested chain (#292):
 *
 * - `calls` nests call arguments, the shape #292 measured:
 *   `wrap($x1['key'], wrap($x2['key'], ...))`.
 * - `array-literals` nests short-array literals for the same staircase:
 *   `[$x1['key'], [$x2['key'], ...]]`.
 *
 * Two construct kinds, because a fix scoped to the one #292 illustrated would
 * pass with only the first. Both are generated rather than committed, for the
 * reason the linear-time test above gives: the shapes only separate a linear
 * implementation from a quadratic one in the thousands.
 */
$arrayAccessorsStaircaseSource = static function (string $shape, int $size): string {
    $opener = $shape === 'array-literals' ? '[$x%d[\'key\'], ' : 'wrap($x%d[\'key\'], ';
    $closer = $shape === 'array-literals' ? ']' : ')';
    $body = '';

    for ($index = 1; $index <= $size; $index++) {
        $body .= sprintf($opener, $index);
    }

    return "<?php\n\n\$out = " . $body . 'null' . str_repeat($closer, $size) . ";\n";
};

/**
 * Runs one staircase through the sniff and returns [reported reads, what the
 * outward walk cost as a delta of the sniff's own counters].
 *
 * Nothing here is timed any more (#354, extending #321): a wall-clock budget
 * states an asymptotic claim only as far as a shared CI runner allows, and #321
 * recorded the same assertion shape failing twice and passing on a third run
 * with no code change. The counters say the same thing exactly, and say it
 * about this sniff alone -- which is what the old parse/sniff timing split was
 * for. PHP_CodeSniffer records the whole chain of enclosing parentheses on
 * every token inside them, so the nested-call staircase costs its tokenizer
 * O(depth²) time and memory -- about 1.1 GB at n=4,000 -- before any sniff
 * runs, and a count of this sniff's own steps is untouched by that where an
 * elapsed time is not.
 *
 * The tokenizer's appetite is why parse() and process() are still driven by
 * hand rather than through analyzeWithSniffs(): the memory limit is raised
 * around them and put back, so it cannot turn this into a fatal on a 128M
 * php.ini.
 *
 * @return array{int, array<string, int>}
 */
$arrayAccessorsStaircaseCounts = static function (
    string $shape,
    int $size
) use ($arrayAccessorsStaircaseSource): array {
    [$config, $ruleset] = buildRuleset([ARRAY_ACCESSORS]);

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-staircase-', true) . '.php';
    file_put_contents($path, $arrayAccessorsStaircaseSource($shape, $size));
    $limit = ini_get('memory_limit');
    ini_set('memory_limit', '2G');

    $sniff = sniffInstance(ARRAY_ACCESSORS);

    try {
        $file = new LocalFile($path, $ruleset, $config);
        $file->parse();

        // buildRuleset() memoises the ruleset, and so the sniff instance, per
        // sniff-code key: this is the same instance every other test in this
        // file drives, so the counters are read as a delta rather than as a
        // total.
        $before = $sniff->cacheCounts();
        $file->process();
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());
        $reported = $file->getErrorCount();
    } finally {
        unlink($path);
    }

    // The tokenizer's maps have to go before the limit does: PHP refuses a
    // limit below current usage, and refusing it is a warning this suite fails
    // on. When they will not free far enough -- the allocator does not always
    // hand pages back -- the raised limit is kept rather than a warning
    // raised; every caller sets it again anyway.
    $file->cleanUp();
    unset($file);
    gc_collect_cycles();

    // "128M" and friends as bytes; a negative limit is no limit at all.
    $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
    $limit = $limit === false ? '-1' : trim($limit);
    $limitBytes = (int) $limit < 0
        ? null
        : ((int) $limit * ($units[strtolower(substr($limit, -1))] ?? 1));

    if ($limitBytes !== null && memory_get_usage(true) < $limitBytes) {
        ini_set('memory_limit', $limit);
    }

    return [$reported, $counted];
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ARRAY_ACCESSORS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Write-side access, existence checks, array literals, $this-rooted reads,
 * and method calls are out of the standard's scope and must stay silent —
 * a false positive on any of them makes the rule unusable.
 *
 * Write-side means every shape the target can take, not just the operator
 * cases: the fixture also covers destructuring (`[$payload['first']] =
 * $source`, `list(...)`, keyed and nested patterns), a reference bind
 * (`$reference = &$payload['name']`), and `foreach` value, key, and
 * pattern targets. A `data_get()` rewrite of any of them either loses the
 * assignment or drops the reference, so flagging one leaves the developer
 * with no compliant form of the statement.
 *
 * The `++` and `&` targets appear twice, plainly and behind a `$` sigil.
 * Both operators precede the chain, so they are only visible from the
 * chain's root — and a variable-variable roots at the sigil, not at the name
 * it dereferences. Rooting at the name hides both operators and flags two
 * writes as reads.
 *
 * One of those targets is deliberately a scope's last statement. Deciding
 * a `foreach` or destructuring target means looking past the chain for the
 * construct enclosing it, and there the search runs all the way to the
 * method's own closing brace. A scope brace and a dynamic member name
 * (`$order->{$name}`) are both curly braces to PHP_CodeSniffer, so
 * confusing the two would read the target as an offset and flag it.
 */
it('does not flag boundary constructs', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        9 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        10 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        11 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        18 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        20 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        27 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        31 => [
            ARRAY_ACCESSORS . '.DirectPropertyAccess',
            ARRAY_ACCESSORS . '.DirectArrayAccess',
        ],
        36 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        43 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        44 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        45 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        52 => [
            ARRAY_ACCESSORS . '.DirectArrayAccess',
            ARRAY_ACCESSORS . '.DirectArrayAccess',
        ],
        53 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        54 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        56 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        60 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        72 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        75 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        78 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        81 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        84 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        85 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        86 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        87 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        106 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        109 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        112 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        115 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        116 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        117 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        118 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        125 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        143 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        159 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        181 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        185 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        207 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        213 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        242 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        248 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        272 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        273 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        293 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        296 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ]);
});

/**
 * A chain reads as one `data_get()` call, so it earns one diagnostic:
 * `$payload['address']['city']` (line 10), `$order->address->city`
 * (line 19), the mixed `$payload['items'][0]->name` (line 36), and the
 * variable property `$order->$field['locale']` (line 43) are each reported
 * once, at the variable the chain is rooted in — line 43 in particular
 * proves `$field` is treated as a link in the chain, not a second root.
 */
it('reports an accessor chain once at its root', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[10])->toHaveCount(1)
        ->and($errors[19])->toHaveCount(1)
        ->and($errors[36])->toHaveCount(1)
        ->and($errors[43])->toHaveCount(1)
        ->and(array_key_first($errors[36]))->toBe(17)
        ->and(array_key_first($errors[43]))->toBe(23);
});

/**
 * A static property roots its own chain: nothing precedes `$registry` that
 * could carry the diagnostic instead, so `self::$registry['key']` is
 * reported at the property (line 44, column 29) rather than skipped.
 */
it('reports a static property chain at the property', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[44])->toHaveCount(1)
        ->and(array_key_first($errors[44]))->toBe(29);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, where an accessor's bracket or
 * dynamic-member brace may never close. The sniff must survive the missing
 * `bracket_closer` and still report the read rather than let it through.
 *
 * Line 27 truncates a chain at the operator itself, leaving no member at
 * all. That is the same ambiguity as line 10's unclosed brace one step
 * earlier -- neither resolves to a property or a method call -- so the two
 * must agree, and reporting is the safe direction for a linter. Dropping it
 * would make the sniff quieter on the more truncated of the two.
 */
it('reports an unterminated chain without falling over', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'unterminated.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        9 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        10 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        27 => [ARRAY_ACCESSORS . '.DirectPropertyAccess'],
    ]);
});

/**
 * The mirror of unterminated.php: a *closer* whose opener was never typed.
 * It carries no `bracket_opener`, so it closes nothing and cannot enclose the
 * chain either -- the walk outward steps over it and the reads on both sides
 * stay reported.
 *
 * The curly brace is the case that has to be handled rather than assumed
 * away. Deciding a curly brace means asking whether it opens a dynamic member
 * name, which reads back from its opener, so taking an opener-less closer for
 * an enclosing construct hands the missing `bracket_opener` -- `null` -- to
 * `isDynamicMemberBrace()`, whose `int $openerPtr` rejects it, and the sniff
 * dies with a TypeError. A TypeError extends Error rather than Exception, so
 * `Runner::processFile()`'s `catch (Exception)` -- the one path that turns a
 * failure into an Internal.Exception report -- never sees it. This harness
 * drives `process()` without a Runner at all, so the TypeError is an uncaught
 * fatal and this test errors rather than fails.
 *
 * (The phpcs command reaches a file-level abort by an earlier route: the
 * runner installs an error handler that rethrows the "Undefined array key"
 * warning preceding the TypeError as a RuntimeException, and that one is an
 * Exception, so it is caught and reported as Internal.Exception. Neither
 * route survives the stray closer -- they only differ in how loudly.)
 *
 * All six reads are asserted rather than a sample: they sit on both sides of
 * the stray closer, so each one pins the walk stepping over it from a
 * different position.
 */
it('steps over a closer whose opener was never typed', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'stray-closer.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        15 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        17 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        21 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        23 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        25 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ])->and($file->getErrorCount())->toBe(6, 'every read survives the stray closer');
});

/**
 * Deciding a chain means walking outward through the constructs enclosing it.
 * That walk once rescanned forward from the chain for every step, which is
 * linear in the distance to the enclosing closer -- so a file of n reads cost
 * O(n^2), each read scanning over every construct that followed it. #239.
 *
 * The fixtures are generated rather than committed because the shapes only
 * separate a linear implementation from a quadratic one in the thousands, and
 * a committed 4,000-statement file is a worse thing for this repository to
 * carry than six lines of str_repeat().
 *
 * The two shapes do different jobs, and saying which is which matters:
 *
 * - `reads` is the discriminating one. It is the ordinary shape -- n
 *   independent reads in one body -- and it is what the rescan actually made
 *   quadratic: measured against the rescan it ran 0.64s at n=500 and 5.27s at
 *   n=2,000, ~3.4x per doubling, and this assertion fails against it.
 * - `nesting` is the depth shape #239 was filed on
 *   (`$target[$target[...]]`). It is a pin, not a reproduction: the rescan
 *   skipped each already-closed sibling construct whole, which made depth
 *   alone flat (0.09s at 500, 0.12s at 2,000) well before this change. It is
 *   asserted so that a future rewrite cannot make depth quadratic unnoticed,
 *   and it would not have failed against the rescan.
 *
 * Counted rather than timed (#354, extending #321). The numbers the old
 * wall-clock budget was set against are kept above as provenance for what the
 * map is worth; the assertion itself now reads the sniff's own counters, as a
 * delta around this one run, because a shared CI runner's jitter can carry any
 * fixed budget with no code change -- #321 recorded this exact assertion shape
 * failing twice and passing on a third run.
 *
 * Each shape is pinned to the walk it actually makes, which is why the expected
 * counts come from the dataset rather than from one formula:
 *
 * - `reads` walks n independent roots at the same depth. Each is one step out,
 *   and the transparent run above them is crossed by the *first* read and read
 *   back by the other n-1 -- one hop for the whole file. Hop by hop it is one
 *   crossing per construct per read, which is the O(n²) this replaced.
 * - `nesting` walks one chain n levels deep. Every step is decided at the
 *   construct it reaches, so the compressing walk is never entered at all, and
 *   the n-1 steps are the pin: a rewrite that made depth quadratic would take
 *   more of them.
 *
 * The reported-read count stays alongside as the non-vacuity half: a file the
 * sniff silently gave up on would be cheap by every counter here.
 *
 * Mutation-checked by recording no answer for the constructs a crossing walks
 * over, so each read crosses the run itself; the hunk and the failure are in
 * this PR's description.
 */
it('decides enclosing constructs in linear time', function (
    string $shape,
    int $size,
    int $expectedSteps,
    int $expectedHops
): void {
    $source = $shape === 'nesting'
        ? "<?php\n\n\$out = " . str_repeat('$target[', $size) . '$key' . str_repeat(']', $size) . ";\n"
        : "<?php\n\nfunction sink(\$row): void\n{\n"
            . str_repeat("    \$value = \$row['key'];\n", $size)
            . "}\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-scale-', true) . '.php';
    file_put_contents($path, $source);

    $sniff = sniffInstance(ARRAY_ACCESSORS);
    $before = $sniff->cacheCounts();

    try {
        $file = analyzeWithSniffs([ARRAY_ACCESSORS], $path);
    } finally {
        unlink($path);
    }

    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    expect($file->getErrorCount())->toBe($size, 'every read is still reported')
        ->and($counted['enclosureVerdict.walks'])->toBe(
            $size,
            "{$shape}: one walk per read, and every read walked"
        )
        ->and($counted['enclosureVerdict.steps'])->toBe(
            $expectedSteps,
            "{$shape}: the outward steps stay linear in n={$size}"
        )
        ->and($counted['decidingStep.hops'])->toBe(
            $expectedHops,
            "{$shape}: a transparent run is crossed once for the file, not once per read"
        );
})->with([
    'n reads in one body' => ['reads', 4000, 4000, 1],
    'n levels of computed offset' => ['nesting', 2000, 1999, 0],
]);

/**
 * The staircase the enclosure map did not fix (#292). The map made each step
 * outward O(1); it did not reduce the number of steps, so n reads at n
 * increasing depths still walked n depths between them -- O(n²), measured at
 * 8.4s for n=4,000 nested calls, matching the 8.136-8.363s #292 reports.
 *
 * Counted rather than timed (#354, extending #321), and the count is what the
 * hazard is actually made of: the number of constructs a read crosses. The
 * compressing walk crosses the run above a read once for the whole file and
 * reads the answer back for every read under it, so the crossings are n. Hop by
 * hop they are n(n+1)/2 -- eight million against four thousand at n=4,000,
 * which is the same claim the seconds were making, stated where a loaded runner
 * cannot reach it.
 *
 * Four sizes, because a single size states a total and not a growth. The counts
 * are asserted exactly at each of them, and being exactly n at 500, 1,000,
 * 2,000 and 4,000 is the per-doubling claim as well: this is the assertion the
 * separate `grows linearly across each doubling` test used to make against the
 * clock, at 2.5x per step against the ~3.7-4.1x #292 measured. A ratio between
 * two exact counts adds nothing to the counts themselves, so it is not
 * asserted twice.
 *
 * Both shapes carry every size here, which the wall-clock form could not do.
 * PHP_CodeSniffer records the chain of enclosing parentheses on every token
 * inside them, so its own tokenizer is O(depth²) in time and memory for nested
 * calls -- 30MB, 84MB, 290MB, 1,128MB at the four sizes, and 2.4x, 2.4x, 2.9x
 * in parse time alone, before a sniff is reached. That arithmetic used to force
 * the per-doubling ratio onto the array-literal shape alone, and the call shape
 * onto a looser parse-relative bound. A count of this sniff's own steps is
 * untouched by the tokenizer's heap, so both shapes are held to the same exact
 * numbers.
 *
 * Every read still being reported is the non-vacuity half: a staircase whose
 * reads went missing would be cheap by every counter here.
 *
 * Mutation-checked by recording no answer for the constructs a crossing walks
 * over; the hunk and the failure are in this PR's description.
 */
it('crosses a staggered staircase once per read at every size', function (string $shape) use (
    $arrayAccessorsStaircaseCounts
): void {
    foreach ([500, 1000, 2000, 4000] as $size) {
        [$errors, $counted] = $arrayAccessorsStaircaseCounts($shape, $size);

        expect($errors)->toBe($size, "{$shape} at n={$size} reports every read")
            ->and($counted['enclosureVerdict.walks'])->toBe(
                $size,
                "{$shape} at n={$size}: one walk per read, and every read walked"
            )
            ->and($counted['decidingStep.hops'])->toBe(
                $size,
                "{$shape} at n={$size}: each read crosses the one construct above it, not the run"
            )
            ->and($counted['decidingStep.hits'])->toBe(
                ($size - 1),
                "{$shape} at n={$size}: every crossing but the first reads back a recorded answer"
            );
    }
})->with([
    'nested call arguments' => 'calls',
    'nested array literals' => 'array-literals',
]);

/**
 * The staggered shape decided against fixtures rather than a clock: the
 * verdicts must not move when the walk stops taking every step.
 *
 * staggered-nesting.php mixes the three ways a staggered read is decided --
 * offset, `foreach` target, assigned destructuring pattern -- and pins the case
 * a compressed walk is most likely to get wrong: chain roots at different
 * depths whose nearest enclosing `foreach` parentheses are the same closer, one
 * before `as` and two after. The closer is transparent for the first and a
 * write target for the second, so no compressed span may carry either answer
 * across `as`. The third is decided by its index before the `foreach` is
 * reached at all.
 *
 * Line 88 is the sharpest of them, and the one the other cases do not reach: a
 * `foreach` header holding a second `foreach`, so a nested `as` sits in the
 * outer header's span ahead of the outer header's own. The header is decided by
 * its own -- the `as` at its own parenthesis depth -- which puts every root in
 * the closure on the subject side of it. $rows (line 88) and $trailing (line
 * 100) are both that subject and both report; $inner (line 89) is the nested
 * header's own target and $outer (line 101) the outer header's, so neither
 * does. Comparing against the nested `as` instead dropped $trailing as a write
 * target, which is the false negative #314 fixes.
 *
 * Line 118 covers the other question the walk answers per read: an existence
 * check two constructs out rather than one. The exemption is the whole chain of
 * enclosing parentheses, not the pair nearest the read, and reading that chain
 * once per opener rather than once per read must not narrow it.
 *
 * The count is asserted alongside the columns because half of what is pinned
 * here is silence: two reads in this fixture are deliberately unreported, and
 * only the count fails when one of them starts reporting.
 *
 * Every expected line and column here was taken from the hop-by-hop walk before
 * it was touched, except line 100 -- the one verdict #314 deliberately reverses.
 */
it('decides a staggered staircase exactly as the hop-by-hop walk did', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'staggered-nesting.php')->getErrors();
    $columnsByLine = [
        25 => [13], 26 => [29, 59],
        34 => [17, 33, 50],
        58 => [31, 60],
        70 => [44, 85],
        88 => [26],
        100 => [24],
        124 => [25, 52],
    ];

    foreach ($columnsByLine as $line => $columns) {
        expect(array_keys($errors[$line]))->toBe($columns, "line {$line} columns");
    }

    expect(array_sum(array_map('count', $errors)))->toBe(14, 'no read gained or lost');
});

/**
 * The mirror of unterminated.php:19 for the staggered shape: an opener that
 * never closes, partway up the staircase rather than beside the read.
 *
 * The walk cannot step over it, so it stops there and reports -- and that has
 * to hold at the depth carrying the unterminated opener and at every depth
 * inside it, not only for the read nearest it. All three reads on the line
 * report: the one outside the `sprintf` call, the one beside the unterminated
 * `foo(`, and the one a further construct deeper. Continuing the walk instead
 * would reach the pattern's `]` and take all three for destructuring targets.
 *
 * A compressed walk is exactly where this can regress: the run of constructs it
 * skips is where the unterminated opener sits. The expected columns were taken
 * from the hop-by-hop walk before it was touched.
 */
it('stops a staggered walk at an unterminated construct partway up it', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'staggered-unterminated.php')->getErrors();

    expect(array_keys($errors[19]))->toBe([2, 35, 65])
        ->and(array_sum(array_map('count', $errors)))->toBe(3, 'every read past the opener survives');
});

/**
 * A dynamic member name hides whether the access is a property or a method
 * call until after the closing brace: `$order->{$field}` (line 45) is a
 * read and is flagged, while `$order->{$name}()` in boundaries.php is a
 * call and is not.
 */
it('flags a dynamic member only when it is not a call', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[45])->toHaveCount(1)
        ->and(array_key_first($errors[45]))->toBe(20);
});

/**
 * A read stays a read when it sits next to a write, which is where the
 * read/write boundary is easiest to get wrong. Each line pins one side of
 * a distinction the sniff has to draw from the token stream:
 *
 * - line 52, `[$payload['id'] => $payload['name']]` — an accessor before
 *   `=>` is the key of an array literal, read to build it. `=>` is a
 *   member of PHPCS's assignment-token set, so treating that set as
 *   "writes" silently drops the key side; both sides must report.
 * - line 53, `$target[$payload['index']] = 'set'` — the read is an index
 *   *into* a write target. The `]` enclosing it closes an index, not a
 *   destructuring pattern, so the trailing `=` does not make it a write.
 * - line 54, `$mask & $payload['flags']` — the `&` is a bitwise and, not
 *   the reference bind that boundaries.php exempts.
 * - line 56, `foreach ($payload['rows'] as $row)` — the subject of a
 *   `foreach` is read; only the `as` clause is assigned into.
 * - line 60, `strtoupper($payload['label'])` — an ordinary call's
 *   parentheses have no owning construct at all, unlike the `foreach` and
 *   `if` parentheses elsewhere in this fixture.
 */
it('still flags reads that sit beside writes', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect(array_keys($errors[52]))->toBe([19, 37])
        ->and(array_key_first($errors[53]))->toBe(17)
        ->and(array_key_first($errors[54]))->toBe(26)
        ->and(array_key_first($errors[56]))->toBe(18)
        ->and(array_key_first($errors[60]))->toBe(29)
        ->and($errors[53])->toHaveCount(1)
        ->and($errors[54])->toHaveCount(1)
        ->and($errors[56])->toHaveCount(1)
        ->and($errors[60])->toHaveCount(1);
});

/**
 * A write target names two chains when its offset is computed:
 * `$target[$payload['index']]` assigns into `$target` and *reads*
 * `$payload` to pick the slot. Only the outer chain is the target, so the
 * inner one stays flagged however the write is spelled.
 *
 * The distinction is only at risk where write-ness is inferred from a
 * construct *enclosing* the chain rather than from an adjacent token,
 * because an enclosing construct covers the offset as well as the target.
 * That is exactly the two paths pinned here — `foreach` targets and
 * destructuring patterns (failing.php:72-87):
 *
 * - 72, `foreach ($rows as $target[$payload['value']])` — value target.
 * - 75, `foreach ($rows as $target[$payload['key']] => $ignored)` — key
 *   target, where `=>` marks the write rather than an array literal.
 * - 78, `foreach ($rows as $order->{$payload['member']})` — dynamic
 *   property target, whose offset sits in a brace rather than brackets.
 * - 84, `[$target[$payload['first']]] = $source` — short-array pattern.
 * - 85, `list($target[$payload['second']]) = $source` — `list()` pattern.
 * - 86, `['x' => $target[$payload['third']]] = $source` — keyed pattern.
 *
 * Lines 81 and 87 wrap the offset in `strtolower(...)`, once per path. The
 * enclosing construct nearest the read is then the call's `)`, not the
 * index `]`, so a search that gave up at the first construct it met would
 * miss the index and drop the read. The offset need not be the innermost
 * enclosing construct, and computing one with a call is ordinary.
 *
 * Each line asserts the count as well as the column, so the write half of
 * the pair is pinned too: a fix that flagged `$target`/`$order` as well
 * would report twice and fail here, as would one that dropped the read.
 */
it('flags computed indexes inside write targets', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    $columnsByLine = [72 => 35, 75 => 35, 78 => 36, 81 => 46, 84 => 18, 85 => 22, 86 => 25, 87 => 29];

    foreach ($columnsByLine as $line => $column) {
        expect($errors[$line])->toHaveCount(1, "line {$line} reports once")
            ->and(array_key_first($errors[$line]))->toBe($column, "line {$line} column");
    }
});

/**
 * The offset expression may carry braces and statements of its own, and
 * neither ends the accessor enclosing it: `match` arms, closures, and
 * anonymous classes are expressions, so a `}` is not the end of the
 * enclosing expression and a `;` inside one is not the end of the enclosing
 * statement. A search bounded by either takes the read for the target and
 * drops it — the whole of failing.php:106-149 reported nothing before the
 * outward walk stopped terminating on them.
 *
 * Each gated path is covered, since only a path that infers write-ness from
 * an enclosing construct can lose the read this way:
 *
 * - 106/115/116, a `match` arm — `foreach` target, short-array and `list()`
 *   patterns.
 * - 117, `strtolower(match ...)` — the deciding index is not the innermost
 *   enclosing construct, and a `match` sits between it and the read.
 * - 109/112/118, a closure and a static closure whose bodies carry `return
 *   ...;` — the `;` that bounded the search before.
 * - 125, an anonymous class with a whole method body in the offset.
 * - 293/296, the dynamic-member column, whose offset sits in a brace.
 *
 * Counts are asserted alongside columns so the write half stays pinned:
 * flagging `$target`/`$order` too would report twice and fail here.
 */
it('flags offsets spanning braces and statements', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    $columnsByLine = [
        106 => 61, 109 => 72, 112 => 79, 115 => 44, 116 => 48,
        117 => 55, 118 => 59, 125 => 32, 293 => 62, 296 => 73,
    ];

    foreach ($columnsByLine as $line => $column) {
        expect($errors[$line])->toHaveCount(1, "line {$line} reports once")
            ->and(array_key_first($errors[$line]))->toBe($column, "line {$line} column");
    }
});

/**
 * A `foreach` header's subject can hold a scope, and a scope can hold a
 * `foreach` of its own — so a nested `as` sits in the header's span alongside
 * the header's own. Only the header's own splits its subject from its target,
 * and it is identified by depth: the innermost parentheses enclosing it are the
 * header's own, which no nested candidate's are (#314).
 *
 * Every root is pinned by the message it carries rather than by a count, since
 * the failure this guards against is one root being decided as another: an
 * aggregate that happened to total the same would not show it.
 *
 * failing.php:175 is the issue's own shape, a closure in the header's subject:
 *
 * - 181, $rows — the header's subject, before the nested `as`. Reports.
 * - 185, $trailing — still the header's subject, after the nested `as` and
 *   before the header's own. Matching the first `as` in the span compared it
 *   against the nested one, took it for the header's write target, and dropped
 *   it. Reports.
 * - $inner (line 182) is the nested header's own target and $outer (line 186)
 *   the outer header's, so the method reports on those two lines and no other.
 */
it('reports a read past a nested foreach clause in a closure', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[181] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$rows')])
        ->and($messages[185] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trailing')])
        ->and(array_intersect_key($messages, array_flip(range(175, 199))))
        ->toHaveKeys([181, 185])
        ->toHaveCount(2, 'no other line in the method reports');
});

/**
 * The same shape with the nested `foreach` in an anonymous class' method rather
 * than a closure (failing.php:201). Depth is what the lookup reads, so the kind
 * of scope holding the nested `foreach` cannot matter — a closure-only check
 * would pass the case above and fail this one.
 *
 * - 207, $anonRows — the header's subject before the nested `as`. Reports.
 * - 213, $anonTrailing — the header's subject after it. Reports.
 * - $anonInner (line 210) and $anonOuter (line 215) are the two headers' own
 *   targets, so the method reports on those two lines and no other.
 */
it('reports a read past a nested foreach clause in an anonymous class', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[207] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$anonRows')])
        ->and($messages[213] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$anonTrailing')])
        ->and(array_intersect_key($messages, array_flip(range(201, 228))))
        ->toHaveKeys([207, 213])
        ->toHaveCount(2, 'no other line in the method reports');
});

/**
 * Two nested `foreach` headers side by side at the same depth, in two closures
 * handed to one call in the header's subject (failing.php:230). The lookup
 * passes over every candidate that is not at the header's own depth, not only
 * the first one it meets: stopping after one skip would settle the header on
 * the second sibling's `as` and drop $secondTrailing.
 *
 * - 242, $firstTrailing — past the first sibling's `as`. Reports.
 * - 248, $secondTrailing — past the second sibling's `as`. Reports.
 * - $firstInner (line 239), $secondInner (line 245) and $siblingOuter (line
 *   250) are targets, so the method reports on those two lines and no other.
 */
it('reports reads past sibling nested foreach clauses', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[242] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$firstTrailing')])
        ->and($messages[248] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$secondTrailing')])
        ->and(array_intersect_key($messages, array_flip(range(230, 263))))
        ->toHaveKeys([242, 248])
        ->toHaveCount(2, 'no other line in the method reports');
});

/**
 * The trap that tells a depth match from "take the last `as` in the span": a
 * nested `foreach` in the header's *target* clause, so its `as` comes after the
 * header's own rather than before it (failing.php:266). Settling the header on
 * the last candidate answers it with the nested `as`, which puts $trapTarget —
 * the header's own write target — on the subject side and reports it as a read.
 *
 * - 272, $trapRows — the header's subject. Reports.
 * - 273, $trapOffset — read to build the target's offset, which is a read
 *   however deep in a write target it sits. Reports.
 * - $trapTarget (line 272) and $trapInner (line 273) are the two headers' own
 *   targets. Both share a line with a read, so each line is pinned to the one
 *   message naming the read: a second message on either line is the target
 *   being reported.
 */
it('does not report a foreach target whose offset holds a nested foreach clause', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[272] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trapRows')])
        ->and($messages[273] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trapOffset')])
        ->and(array_intersect_key($messages, array_flip(range(266, 285))))
        ->toHaveKeys([272, 273])
        ->toHaveCount(2, 'no other line in the method reports');
});

/**
 * The nesting inverts as readily as it nests: a closure can put a whole
 * write statement *inside* an accessor's offset, so a `foreach` or
 * destructuring target sits within an index rather than around one. The
 * target is still a target, because the construct assigning into it
 * encloses it more tightly than the index does.
 *
 * boundaries.php pins both directions (its assignsInsideAnAccessorOffset()
 * against the offset reads on failing.php:106-149). Deciding by the
 * innermost enclosing construct is what keeps them apart: a walk that
 * asked only "is an index bracket anywhere outside me?" would answer yes
 * for the inverted shapes and flag a write, and one that asked only "is a
 * `foreach` anywhere outside me?" would drop every offset read.
 */
it('does not flag write targets nested inside an accessor offset', function (): void {
    expect(analyzeFixture(ARRAY_ACCESSORS, 'boundaries.php')->getErrors())->toBe([]);
});

/**
 * Records a PHP_CodeSniffer defect the sniff cannot see past, so an
 * upstream fix surfaces as a failure here rather than silently.
 *
 * A `foreach` whose target is a dynamic member holding a brace-bearing
 * expression leaves the loop's scope unrecorded, and the tokenizer then
 * labels the *next* statement's destructuring pattern T_OPEN_SQUARE_BRACKET
 * instead of T_OPEN_SHORT_ARRAY. That label is the only thing separating an
 * index (a read) from a pattern (a write), so line 30's write target
 * `$target` is reported alongside the genuine read of `$payload` — two
 * violations where the same statement, after an ordinary dynamic member,
 * correctly yields one (line 43).
 *
 * It is pinned rather than worked around: the mislabelling happens before
 * any sniff runs, and reconstructing the distinction would mean re-deriving
 * it from token data already known to be wrong.
 */
it('records a tokenizer scope defect rather than working around it', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'tokenizer-limits.php')->getErrors();

    expect(array_keys($errors[30]))->toBe([10, 18], 'the defect adds a false positive')
        ->and(array_keys($errors[43]))->toBe([18], 'the same statement is correct without it');
});

/**
 * A statement PHP would reject must not suppress a read the sniff would
 * otherwise report. `doSomething($payload['key']) = $default` (line 11) is
 * a parse error — a call is not an assignable target — and only `list()`
 * parentheses close a destructuring pattern, so the trailing `=` says
 * nothing about the argument. It reports exactly as its well-formed
 * counterpart on line 12 does.
 *
 * The two lines differ in what makes the parentheses ineligible, which is
 * why both are here. Line 11's belong to an ordinary call and have no
 * owning construct at all; line 19's `if ($payload['key']) = $default`
 * *has* an owner, just not `list()`. Testing the owner's type rather than
 * its presence is the only thing keeping line 19's read reported — accept
 * any owned parenthesis and the `=` is read as assigning to the condition,
 * silently dropping it.
 */
it('still reports the read in a malformed assignment', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'malformed.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        11 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        12 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ]);
});

/**
 * An existence check exempts what sits inside its own parentheses, not the
 * condition around them. `if (isset($payload['present']) && $payload['live']
 * === 'x')` (failing.php:143) is the pairing that pins the scope: the
 * `isset()` argument is silent while the read beside it reports, which is
 * the read/exempt twin of the reads-beside-writes test.
 *
 * boundaries.php covers the exempt half alone, so nothing there fails if the
 * check widens from "inside the isset parentheses" to "anywhere in the
 * enclosing condition". Here, widening it drops line 143 entirely.
 */
it('still flags a read beside an existence check', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[143])->toHaveCount(1)
        ->and(array_key_first($errors[143]))->toBe(43);
});

/**
 * A variable-variable roots the chain at its `$` sigil, not at the name the
 * sigil dereferences: PHP 7's uniform variable syntax reads
 * `$$name['key']` (failing.php:159) as `($$name)['key']`.
 *
 * The message is asserted, not just the position, because the subject is
 * the whole point. `$name` holds the *name* of the array, so advising
 * `data_get($name, ...)` would send the developer to search a string, which
 * always returns the fallback — advice that cannot be applied is worse than
 * none. Column 16 is the sigil; the variable begins at 17.
 */
it('reports a variable-variable chain at its sigil', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[159])->toHaveCount(1)
        ->and(array_key_first($errors[159]))->toBe(16)
        ->and($errors[159][16][0]['message'])->toContain('data_get($$name, ...)');
});

/**
 * A file can end on a bare variable with no accessor after it to classify,
 * and the sniff must pass over it. The guard doing so is not decorative:
 * `readAccessCode()` takes an int, so without it the missing accessor
 * arrives as `false` and the sniff dies with a TypeError — this test errors
 * rather than fails if the guard is removed.
 */
it('does not report a file ending on a bare variable', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'trailing-variable.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the detection-only decision: rewriting a chain to a dotted
 * `data_get()` path is a judgement call, so no violation is auto-fixable.
 */
it('reports detection-only violations', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'failing.php');

    expect($file->getErrorCount())->toBe(47)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The enclosure map this sniff builds once per token stream must not answer one
 * analysis with another analysis's pointers (#343).
 *
 * The map used to be keyed by file name, token count and fixer-loop counter.
 * Two sources analysed as STDIN report the same name, so two of them that also
 * tokenise to the same count shared one key — and a single `Ruleset` reused
 * across several analyses, which is what buildRuleset()'s memoisation gives
 * every call below, hands them one sniff instance and one map.
 *
 * The two sources here tokenise to 25 tokens each — `isset` and `strlen` are
 * one token apiece — and differ in exactly what the map records: A's read sits
 * inside an existence check, which the standard exempts, while B's sits inside
 * an ordinary call, which it does not. Under the old key B was measured against
 * A's map, inherited the exemption, and its line-3 violation was never
 * reported. That is the assertion below: B owes two violations, not the one A
 * owes.
 *
 * The third call is what separates a working key from no cache at all: it
 * re-analyses A and requires A's single violation back, which a sniff that had
 * simply stopped caching would also give — but a sniff whose map leaked between
 * streams would not, since B's stream would by then have overwritten it.
 */
it('keeps its enclosure map from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        $one = isset($alpha['beta']);
        $two = $gamma['delta'];

        PHP;

    $sourceB = <<<'PHP'
        <?php

        $one = strlen($alpha['beta']);
        $two = $gamma['delta'];

        PHP;

    $first = analyzeStdinSource([ARRAY_ACCESSORS], $sourceA);
    $second = analyzeStdinSource([ARRAY_ACCESSORS], $sourceB);
    $third = analyzeStdinSource([ARRAY_ACCESSORS], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 3, 'column' => 15, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
            ['line' => 4, 'column' => 8, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            3 => [sprintf(ARRAY_ACCESSORS_READ, '$alpha')],
            4 => [sprintf(ARRAY_ACCESSORS_READ, '$gamma')],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([
            ['line' => 4, 'column' => 8, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
        ])
        ->and(violationMessagesByLine($third->getErrors()))->toBe([
            4 => [sprintf(ARRAY_ACCESSORS_READ, '$gamma')],
        ]);
});

/**
 * The enclosure map is built once for a token stream and read from for the rest
 * of it, rather than rebuilt on every read (#343).
 *
 * The test above proves the key never answers one analysis with another's
 * pointers. It cannot prove the other half of what a key is for, and neither
 * can any other black-box test: a sniff that rebuilt the map on every single
 * read would report exactly the same violations, only slower — which is the
 * O(n²) cost buildEnclosureMap() exists to remove. Every analysis there also
 * constructs its own DummyFile, so all three get their own identity from
 * TokenStreams::key() and miss by design.
 *
 * Reuse is observable only from inside the sniff, so the sniff counts it, the
 * way UnusedFormalParameterSniff already counts its own indexes. Both numbers
 * are pinned, and each rules out a different failure:
 *
 * - one build per stream, at any size, is the claim itself;
 * - 2n-1 hits keeps it from passing vacuously, since a sniff that stopped
 *   consulting the map at all would report one build and no hits. Each read
 *   below reaches the map twice — once from enclosureVerdict(), once from
 *   isInsideExistenceCheck() — so n reads total 2n, of which one builds and
 *   2n-1 hit. The build is one the guard never had to answer.
 *
 * Mutation-checked by deleting the `$this->enclosureMapKey === $key` guard, so
 * every read rebuilds: `composer test` then fails here at the smallest size,
 * n=2, reading 4 builds / 0 hits against the 1 / 3 asserted; n=4 reads 8 / 0
 * against 1 / 7, and n=8 reads 16 / 0 against 1 / 15.
 */
it('builds its enclosure map once per stream, not once per read', function (): void {
    $sniff = sniffInstance(ARRAY_ACCESSORS);

    foreach ([2, 4, 8] as $size) {
        $reads = '';

        for ($index = 0; $index < $size; $index++) {
            $reads .= "\$one{$index} = \$alpha{$index}['beta'];\n";
        }

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([ARRAY_ACCESSORS], "<?php\n\n" . $reads);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every read is still reported")
            ->and($counted['enclosureMap.builds'])->toBe(
                1,
                "n={$size}: the map is built once for the stream, not once per read"
            )
            ->and($counted['enclosureMap.hits'])->toBe(
                (2 * $size) - 1,
                "n={$size}: every read after the first answers from the map already built"
            );
    }
});
