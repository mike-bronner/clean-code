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

const ARRAY_ACCESSORS = 'CleanCode.Arrays.ArrayAccessors';

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
        174 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        177 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
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
 * The budget is wall clock, so it is set generously: the map answers n=4,000
 * in about a fifth of a second here, which leaves better than an order of
 * magnitude of headroom for a loaded CI runner, while the rescan needs tens of
 * seconds for the same file and cannot pass by being unlucky.
 */
it('decides enclosing constructs in linear time', function (string $shape, int $size): void {
    $source = $shape === 'nesting'
        ? "<?php\n\n\$out = " . str_repeat('$target[', $size) . '$key' . str_repeat(']', $size) . ";\n"
        : "<?php\n\nfunction sink(\$row): void\n{\n"
            . str_repeat("    \$value = \$row['key'];\n", $size)
            . "}\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-scale-', true) . '.php';
    file_put_contents($path, $source);

    try {
        $startedAt = hrtime(true);
        $file = analyzeWithSniffs([ARRAY_ACCESSORS], $path);
        $elapsed = (hrtime(true) - $startedAt) / 1e9;
    } finally {
        unlink($path);
    }

    expect($file->getErrorCount())->toBe($size, 'every read is still reported')
        ->and($elapsed)->toBeLessThan(3.0, "{$shape} at n={$size} took {$elapsed}s");
})->with([
    'n reads in one body' => ['reads', 4000],
    'n levels of computed offset' => ['nesting', 2000],
]);

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
 * - 174/177, the dynamic-member column, whose offset sits in a brace.
 *
 * Counts are asserted alongside columns so the write half stays pinned:
 * flagging `$target`/`$order` too would report twice and fail here.
 */
it('flags offsets spanning braces and statements', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    $columnsByLine = [
        106 => 61, 109 => 72, 112 => 79, 115 => 44, 116 => 48,
        117 => 55, 118 => 59, 125 => 32, 174 => 62, 177 => 73,
    ];

    foreach ($columnsByLine as $line => $column) {
        expect($errors[$line])->toHaveCount(1, "line {$line} reports once")
            ->and(array_key_first($errors[$line]))->toBe($column, "line {$line} column");
    }
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

    expect($file->getErrorCount())->toBe(39)
        ->and($file->getFixableCount())->toBe(0);
});
