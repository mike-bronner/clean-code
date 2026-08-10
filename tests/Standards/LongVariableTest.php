<?php

/**
 * Tests the custom CleanCode.Naming.LongVariable sniff, which replicates
 * PHPMD's Naming/LongVariable rule (docs/phpmd/naming-longvariable.md).
 *
 * Every expectation below was checked against a live PHPMD 2.15.0 run over the
 * very same fixtures, with the same properties: `passing.php` produces no PHPMD
 * violation, `failing.php` produces one per shape at the lines pinned here,
 * `subtraction.php` produces six with default properties and exactly two once
 * the prefix/suffix lists are configured, and `ordering.php` produces one. The
 * point of the sniff is parity, so the fixtures are shaped to be runnable by
 * both tools.
 *
 * `divergences.php` is the exception, and the reason it exists: it is the one
 * fixture where the two tools deliberately disagree, so its test asserts what
 * *this* sniff does and states what PHPMD does instead.
 *
 * The rule is detection-only — renaming a variable means rewriting every
 * reference to it, and for a field every reference across the codebase — so
 * there is no autofixed fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const LONG_VARIABLE = 'CleanCode.Naming.LongVariable';

const LONG_VARIABLE_TOO_LONG = 'CleanCode.Naming.LongVariable.TooLong';

/**
 * Prefix and suffix lists for subtraction.php. `MockCollection` sits *after*
 * `Collection` deliberately: PHPMD stops at the first matching suffix, so the
 * longer entry must never win.
 */
const LONG_VARIABLE_SUBTRACTIONS = [
    'subtractPrefixes' => 'temporary',
    'subtractSuffixes' => 'Collection,Factory,MockCollection',
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LONG_VARIABLE);
});

/**
 * passing.php is the silent half of the boundary and the sniff's near-miss
 * inventory at once. Every field, parameter, and local in it is exactly 20
 * bytes without the leading `$` — the default maximum, which PHPMD reports
 * above rather than at — and around them sit the shapes the sniff must not
 * measure: a 45-byte class constant, a 52-byte method name, both sides of five
 * member accesses, and three names of 38 to 40 bytes at file scope.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The flagged half of the boundary. Each name is 21 bytes without its `$`, one
 * over the maximum, and each arrives by a different route: a field, a static
 * field, a promoted constructor property, a parameter, a local, a `for`
 * variable, a `foreach` variable, a caught exception, a destructured variable,
 * a closure `use` variable, a closure parameter, a `global`, a function-static,
 * an interface method's parameter, an enum method's parameter and local, and a
 * plain function's parameter and local.
 *
 * Columns are asserted alongside lines because the sniff reports at the
 * variable token itself, not at the statement — so a report points at the name
 * that has to change.
 */
it('flags every over-length variable at its own token', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 22, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 18, 'column' => 26, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 24, 'column' => 47, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 28, 'column' => 34, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 30, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 32, 'column' => 14, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 36, 'column' => 28, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 45, 'column' => 29, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 49, 'column' => 10, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 60, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 62, 'column' => 30, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 69, 'column' => 16, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 71, 'column' => 16, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 79, 'column' => 36, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 86, 'column' => 34, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 88, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 94, 'column' => 37, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 96, 'column' => 5, 'source' => LONG_VARIABLE_TOO_LONG],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * A name is reported once per container, at its first occurrence, not once per
 * use. Three names in failing.php are used more than once — the `for` variable
 * on line 32 appears three times on that one line, the `foreach` variable is
 * read on line 37, and the closure `use` variable is read again on line 63 —
 * and each still accounts for exactly one of the eighteen reports above.
 * Deleting the de-duplication check and re-running turns those eighteen reports
 * into thirty-three.
 */
it('reports each name once per container', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))
        ->toBe(array_fill_keys([16, 18, 24, 28, 30, 32, 36, 45, 49, 60, 62, 69, 71, 79, 86, 88, 94, 96], 1));
});

/**
 * The message carries the offending name, its measured length, and the
 * threshold, so a report says why the name failed rather than only that it did.
 * The name is quoted with its `$` — that is what the reader has to search for —
 * while the length is the name without it, which is what PHPMD measures.
 */
it('names the variable, its length, and the threshold', function (): void {
    $errors = analyzeFixture(LONG_VARIABLE, 'failing.php')->getErrors();

    expect($errors[16][22][0]['message'])
        ->toBe('Name $replenishmentWindowId is 21 characters long; keep it to 20 or fewer');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect($file->getErrorCount())->toBe(18)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The threshold is read from the property rather than hardcoded. Lowering the
 * maximum to 19 flags exactly the seven 20-byte declarations in passing.php and
 * nothing else.
 *
 * That "nothing else" is the load-bearing half. The three file-scope names on
 * lines 103 to 106 are 38 to 40 bytes — far over *either* threshold — and stay
 * silent at 19 just as they did at 20, which is only possible if they are
 * excluded for *where* they are rather than for how long they are. The same
 * goes for the member accesses and for the constant and method names: none of
 * them appears at either threshold. PHPMD reports the identical seven lines
 * with `maximum` set to 19.
 */
it('reads the maximum from its property', function (): void {
    $file = analyzeFixtureWithProperty(LONG_VARIABLE, 'passing.php', 'maximum', 19);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        27 => [LONG_VARIABLE_TOO_LONG],
        32 => [LONG_VARIABLE_TOO_LONG],
        46 => [LONG_VARIABLE_TOO_LONG],
        48 => [LONG_VARIABLE_TOO_LONG],
        77 => [LONG_VARIABLE_TOO_LONG],
        88 => [LONG_VARIABLE_TOO_LONG],
        90 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

/**
 * Raising the maximum to 21 silences every name in failing.php, all of which
 * are exactly 21 bytes. Together with the test above, this pins the comparison
 * as "strictly greater than the maximum" from both sides: at 21 the 21-byte
 * names pass, at 20 they fail.
 *
 * It also pins the `$` as excluded from the measurement. Were the token counted
 * whole, these names would measure 22 and every one of them would still be
 * reported here.
 */
it('stays silent at exactly the maximum', function (): void {
    $file = analyzeFixtureWithProperty(LONG_VARIABLE, 'failing.php', 'maximum', 21);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The unconfigured control for subtraction.php: with the default empty prefix
 * and suffix lists, every one of its six fields is over the maximum. The
 * configured runs below have to be read against this — without it, a sniff that
 * had simply fallen silent on the fixture would look like working subtraction.
 */
it('flags every name in the subtraction fixture without the lists configured', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'subtraction.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([22, 27, 34, 40, 48, 59]);
});

/**
 * Configured, four of those six fall to 20 bytes or fewer and go silent: line
 * 22 by the prefix, 27 by the first suffix in the list, 34 by the second (so
 * the whole list is read, not just its head), and 40 by both at once.
 *
 * The two that remain are the point of the test. Line 48 ends in
 * `MockCollection`, but `Collection` matches first and PHPMD stops there — 10
 * bytes come off rather than 14, leaving 21. A subtraction that preferred the
 * longest match, or that did not stop at the first, would silence it. Line 59
 * has both a matching prefix and a matching suffix and is still 23 bytes after
 * both: subtraction shortens a name, it does not exempt one.
 */
it('subtracts one prefix and one suffix, each the first that matches', function (): void {
    $file = analyzeFixture(
        LONG_VARIABLE,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_VARIABLE_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_VARIABLE_SUBTRACTIONS['subtractSuffixes'];
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        48 => [LONG_VARIABLE_TOO_LONG],
        59 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

/**
 * The same two lines, configured the way a *consuming ruleset* configures them:
 * as strings through `Ruleset::setSniffProperty()`, which is the path a
 * `<properties>` element in rules.xml actually takes. The test above assigns to
 * the properties directly and so always hands over a correctly typed value;
 * only this path proves the sniff is configurable from XML at all, which is
 * what a consumer of this package does.
 */
it('takes its lists from ruleset properties', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        LONG_VARIABLE,
        'subtraction.php',
        LONG_VARIABLE_SUBTRACTIONS
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        48 => [LONG_VARIABLE_TOO_LONG],
        59 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

/**
 * The prefix list stops at its first match too. `temporaryWarehouseInventory`
 * sits after `temporary` and both match line 59, so 9 bytes come off rather
 * than 29, leaving 33 — still over the maximum. A loop that carried on past the
 * first match would subtract both, leave 4, and silence the line.
 *
 * This is the prefix-side twin of the `Collection` / `MockCollection` case
 * above. The two loops are separate, so each needs its own case: pinning one
 * says nothing about the other.
 */
it('stops at the first matching prefix', function (): void {
    $file = analyzeFixtureWithProperty(
        LONG_VARIABLE,
        'subtraction.php',
        'subtractPrefixes',
        'temporary,temporaryWarehouseInventory'
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([27, 34, 48, 59]);
});

/**
 * Both remaining reports state the *subtracted* length, not the raw one: 31 and
 * 42 bytes respectively, measured as 21 and 23 once their prefixes and suffixes
 * come off. A sniff that subtracted correctly for the comparison but reported
 * the raw length would pass the test above and fail here.
 */
it('reports the subtracted length', function (): void {
    $errors = analyzeFixture(
        LONG_VARIABLE,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_VARIABLE_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_VARIABLE_SUBTRACTIONS['subtractSuffixes'];
        }
    )->getErrors();

    expect($errors[48][21][0]['message'])
        ->toBe('Name $warehouseAuditLogMockCollection is 21 characters long; keep it to 20 or fewer')
        ->and($errors[59][21][0]['message'])
        ->toBe(
            'Name $temporaryWarehouseInventoryAuditCollection'
            . ' is 23 characters long; keep it to 20 or fewer'
        );
});

/**
 * Entries are trimmed and empty ones dropped, as PHPMD's own
 * Strings::splitToList() does. Both halves are pinned on the *prefix* list,
 * because that is the side where the empty entry does damage: an empty prefix
 * satisfies `strncmp($name, '', 0) === 0` for every name, so it matches first,
 * subtracts nothing, and — with the loop stopping at the first match — strands
 * every real prefix behind it. (An empty *suffix* is inert by comparison:
 * `substr($name, -0)` returns the whole name, which never equals `''`.)
 *
 * Configured with padding and stray commas around a single `temporary`, lines
 * 22 and 40 still lose their prefix and go silent while the other four stay
 * flagged. Drop the trim and `temporary` no longer matches; drop the
 * empty-entry filter and the empty prefix wins the race — either way both lines
 * come back.
 */
it('trims list entries and drops empty ones', function (): void {
    $file = analyzeFixtureWithProperty(
        LONG_VARIABLE,
        'subtraction.php',
        'subtractPrefixes',
        ' , temporary , '
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([27, 34, 48, 59]);
});

/**
 * PHPMD marks a name as seen *before* deciding whether to exempt the
 * occurrence, so whether an over-long name is reported at all depends on what
 * its first occurrence happens to be. ordering.php puts the same 21-byte case
 * both ways round in two methods; only the second is reported, in this sniff
 * and in PHPMD 2.15.0 alike.
 *
 * The assertion is the whole file rather than the one line, because the point
 * is as much what stays silent as what does not: swap the two statements in
 * `report()` so the exemption is checked before the name is recorded, and line
 * 29 joins line 41 here.
 */
it('reproduces PHPMD ordering of de-duplication and the member-access exemption', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'ordering.php');

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([41 => [LONG_VARIABLE_TOO_LONG]]);
});

/**
 * PHP_CodeSniffer opens no scope for a PHP 8.4 property hook, so every
 * parameter and local written inside one reaches the class-body walk looking
 * class-scoped. The walk therefore keeps only statements that open with a
 * visibility or property modifier.
 *
 * All three names in the fixture are 21 bytes. A hooked property is still a
 * property declaration and is reported (line 28); the hook's local (line 30)
 * and the hook's `set` parameter (line 41) are not.
 *
 * Line 46 is the assertion that does the work. It is the real field, and it
 * shares its name with the hook local declared sixteen lines above it — so
 * dropping the filter does not merely add two reports, it *removes* this one,
 * the earlier hook local having de-duplicated the field away. Verified by
 * deleting the filter and re-running: lines 28, 30, and 41 report, and 46 does
 * not.
 *
 * PHPMD 2.15.0 cannot parse a file containing a hook at all, so there is no
 * parity claim here — this is a gap, not a divergence.
 */
it('measures a hooked property but not the insides of its hooks', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'property-hooks.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        28 => [LONG_VARIABLE_TOO_LONG],
        46 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

/**
 * The two deliberate divergences from PHPMD, pinned so neither can drift
 * unnoticed. On this fixture PHPMD 2.15.0 reports seven violations —
 * lines 26, 28, 28, 30, 30, 51, and 51 — and this sniff reports three.
 *
 * - The trait's parameter (28) and local (30) are reported *once* here and
 *   twice by PHPMD, whose `apply()` walks a trait node whole and then walks
 *   each of its methods again. The trait's field (26) is reported once by both.
 * - Neither name interpolated into the string on line 51 is reported here.
 *   PHPCS hands the sniff the whole string as one token, so there is no
 *   variable token to find.
 */
it('diverges from PHPMD only on trait duplicates and string interpolation', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'divergences.php');

    expect(violationCountsByLine($file->getErrors()))
        ->toBe([26 => 1, 28 => 1, 30 => 1]);
});
