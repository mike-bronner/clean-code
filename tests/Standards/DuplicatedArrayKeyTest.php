<?php

/**
 * Tests the custom CleanCode.Arrays.DuplicatedArrayKey sniff, which replicates
 * PHPMD's Clean Code rule of the same name — see
 * docs/phpmd/cleancode-duplicatedarraykey.md.
 *
 * Every expectation below was checked against PHPMD 2.15.0 running
 * rulesets/cleancode.xml/DuplicatedArrayKey over the same fixtures (wrapped in
 * a function, since PHPMD's rule is method- and function-aware). The shapes the
 * two tools disagree on are pinned separately, in divergences.php.
 *
 * The rule is detection-only, so there is no autofixed fixture and the
 * detection-only test pins that.
 */

declare(strict_types=1);

const DUPLICATED_ARRAY_KEY = 'CleanCode.Arrays.DuplicatedArrayKey';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DUPLICATED_ARRAY_KEY);
});

/**
 * passing.php is discriminating rather than merely quiet: it holds distinct
 * keys in every literal form the sniff resolves — each integer base, both
 * quote styles, the coerced `false`/`true`/`null`, and the int-like strings
 * that stay strings — alongside the near-miss shapes the sniff must stay
 * silent on. Those near misses are deliberately *repeated* keys the sniff
 * declines to resolve: constants, class constants, variables, expressions,
 * escaped and interpolated double-quoted strings, a negated string, a
 * non-finite float, and an integer literal that leaves the integer range while
 * naming its digits in hexadecimal, binary or either octal spelling, plain and
 * negated. Each one is an early return, so the fixture's silence is a verdict
 * about them rather than the absence of anything to look at.
 *
 * The out-of-range group is one that fails loudly when its guard goes: without
 * it the cast reads every hexadecimal, binary and modern-octal literal there as
 * 0.0 and every legacy-octal one as 1.0E+21, which makes each pair a duplicate
 * on a key PHP never used and turns this fixture red.
 *
 * The malformed-octal group at the end is the other. Those literals do not
 * compile at all — `php -l` calls `089` an invalid numeric literal — but
 * PHP_CodeSniffer tokenises rather than compiles, so the sniff sees them, and
 * each is written twice. Without the digit check, octdec() ignores the illegal
 * digits and answers 0 for all three literals, which makes every pair a
 * duplicate here and turns this fixture red in process; through the shipped
 * binary the diagnostic octdec() raises aborts the file instead, which the
 * installed-run test below pins separately.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every duplicate is reported at the later, overriding key — the entry that
 * wins at runtime — with the column of the key token itself.
 *
 * Lines 29 to 32 are one array whose five keys are 15 written in five ways
 * (decimal, hex, binary, octal, underscore-separated); lines 38 and 40 are the
 * float truncation toward zero in both directions; line 47 is a literal too
 * large for the integer range, which PHP casts to the same key both times.
 * Lines 54 and 55 are the same key written three times, so it is reported
 * twice. Line 59 is the long array form, lines 65 and 67 are a nested array
 * and its parent reported independently, line 72 is a keyed destructuring
 * pattern, and lines 79 and 81 are single-quoted keys whose escape sequences
 * make them the same keys as their neighbours. Lines 102, 110, 118 and 125 are
 * the wrap's own boundaries, each reached by a literal the entry above it does
 * not repeat.
 *
 * Lines 135, 136 and 144 are float literals a leading zero does not make octal,
 * and they are here rather than in the compliant fixture because reading that
 * zero as an octal marker silences a real duplicate: each of the three shares
 * its key with the plain literal above it. Lines 151 to 154 are the same
 * number written in the four non-decimal bases with a digit separator inside
 * its digits, so a separator removed after the digits are read — rather than
 * before — silences all four.
 */
it('flags every duplicate key at the overriding entry', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => DUPLICATED_ARRAY_KEY . '.Found',
        ],
        [
            [7, 5], [9, 5], [16, 5], [17, 5], [23, 5], [29, 5], [30, 5], [31, 5],
            [32, 5], [38, 5], [40, 5], [47, 5], [54, 5], [55, 5], [59, 25],
            [65, 9], [67, 5], [72, 17], [79, 5], [81, 5], [91, 5], [93, 5],
            [102, 5], [110, 5], [118, 5], [125, 5], [135, 5], [136, 5],
            [144, 5], [151, 5], [152, 5], [153, 5], [154, 5],
        ]
    ))->and($file->getWarnings())->toBe([]);
});

/**
 * The message names the key as PHP stores it, not as either entry spells it:
 * `false` and `0` are both reported as the key 0, `'1'` and `true` as 1, and
 * `null` as the empty string. That is the point of the rule — the two entries
 * are the same key however differently they are written.
 */
it('names the coerced key and the declaration it overrides', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[7])->toBe(['Duplicate array key 0 overrides the entry on line 6; remove one of them'])
        ->and($messages[9])->toBe(["Duplicate array key 'foo' overrides the entry on line 8; remove one of them"])
        ->and($messages[16])->toBe(['Duplicate array key 1 overrides the entry on line 15; remove one of them'])
        ->and($messages[17])->toBe(['Duplicate array key 1 overrides the entry on line 15; remove one of them'])
        ->and($messages[23])->toBe(["Duplicate array key '' overrides the entry on line 22; remove one of them"])
        ->and($messages[30])->toBe(['Duplicate array key 15 overrides the entry on line 28; remove one of them']);
});

/**
 * A key written three times is reported twice, and both reports name the first
 * declaration rather than the entry immediately above. The first declaration
 * is the one kept, which is what PHPMD does too.
 */
it('reports every repeat against the first declaration', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[54])->toBe(["Duplicate array key 'k' overrides the entry on line 53; remove one of them"])
        ->and($messages[55])->toBe(["Duplicate array key 'k' overrides the entry on line 53; remove one of them"]);
});

/**
 * A finite float key outside the integer range resolves to the key PHP itself
 * would store it under, which is the value wrapped modulo 2**64 rather than the
 * end of the range. Asserted through the reported key, because the resolution
 * is private and this package forbids Reflection in its own tests
 * (CleanCode.Testing.NoReflectionAccess).
 *
 * Discriminating in both directions, and that is the point of using three
 * literals rather than one. Leaving an out-of-range literal unresolved reports
 * nothing here at all. Saturating one onto PHP_INT_MAX/PHP_INT_MIN instead of
 * wrapping makes 1e30 and 2e30 the same key and adds a report on line 90 that
 * PHP would not agree with. Only the wrap gives exactly these reports with
 * exactly these keys.
 *
 * Every key named below is the one the interpreter itself puts the entry in,
 * read off `array_keys()` of the same literals evaluated by PHP, not computed
 * by the arithmetic under test. PHP 8.4.24 and 8.5.9 agree on all of them.
 *
 * The last four pairs stand at the wrap's own boundaries, and each reaches its
 * shared key from two literals that are not the same text — the pair on line 47
 * repeats one spelling, so an error that moved every magnitude by the same
 * amount would keep it a duplicate and go unseen if the key itself were not
 * asserted. Which mutation each line answers, measured one at a time against
 * the shipped fixture rather than argued:
 *
 * - line 47, 2**63 twice. The first magnitude the direct cast cannot take.
 *   Folding by 2**63 instead of 2**64 reports key 0 here, and saturating onto
 *   the range ends reports PHP_INT_MAX; the pair stays a duplicate under both,
 *   which is why the key and not the report is the assertion.
 * - line 102, 2**63 against 3 * 2**63. One key from two distances, so the fold
 *   is pinned as a subtraction of 2**64 rather than a jump to PHP_INT_MIN.
 *   Moves to 0 under either wrong multiple and to PHP_INT_MAX under saturation.
 * - line 110, 2**64 against a plain `0`. A whole turn of the wrap. Goes silent
 *   under saturation and under leaving an out-of-range literal unresolved. It
 *   survives both modulus mutations, which land it back on 0 by coincidence —
 *   this line answers saturation, not the modulus.
 * - line 118, -2**63 against -3 * 2**63. The sign carried into the resolution.
 *   PHP_INT_MIN has no integer negation, so negating after the wrap leaves the
 *   range again; the resulting diagnostic is fatal only under the installed
 *   run, which is where that is pinned. Goes silent here under a wrong fold
 *   multiple and under leaving the literal unresolved.
 * - line 125, -2e30 against the plain literal of the key it wraps onto. The
 *   lift that raises a remainder below -2**63 into [0, 2**64) before the fold.
 *   Goes silent under saturation and under leaving the literal unresolved.
 *   Dropping the lift alone leaves this key intact and shows only on 8.5,
 *   again through the installed run.
 *
 * What this test does *not* pin is the PHP 8.5 abort, and the distinction is
 * worth stating because it is not visible from here. analyzeFixture() drives a
 * LocalFile in this process, and PHP_CodeSniffer only installs the error
 * handler that turns a warning raised inside a sniff into an exception when it
 * runs through Runner. So the bare cast this replaced passes every assertion
 * below even on 8.5 — confirmed by putting it back and watching this test stay
 * green. The abort is pinned by the sibling test underneath, which spends a
 * subprocess to get the real handler.
 */
it('resolves a float key outside the integer range the way PHP does', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[91])
        ->toBe(['Duplicate array key 5076964154930102272 overrides the entry on line 89; remove one of them'])
        ->and($messages[93])
        ->toBe(['Duplicate array key -5076964154930102272 overrides the entry on line 92; remove one of them'])
        ->and($messages)->not->toHaveKey(90)
        ->and($messages[47])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 46; remove one of them'])
        ->and($messages[102])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 101; remove one of them'])
        ->and($messages[110])
        ->toBe(['Duplicate array key 0 overrides the entry on line 109; remove one of them'])
        ->and($messages[118])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 117; remove one of them'])
        ->and($messages[125])
        ->toBe(['Duplicate array key 8292815763849347072 overrides the entry on line 124; remove one of them']);
});

/**
 * The same keys through the shipped binary, which is where resolving them can
 * abort the run rather than merely answer differently.
 *
 * PHP 8.5 raises `The float ... is not representable as an int, cast occurred`
 * as an E_WARNING for a cast the earlier PHPs performed silently. Runner
 * installs an error handler that rethrows any diagnostic raised inside a sniff,
 * File catches it and records Internal.Exception, and processing of that file
 * stops there — so on 8.5 the cast did not change one message, it replaced the
 * whole file's report with a single "an error occurred during processing". A
 * report that names the sniff's own code is therefore the assertion, and
 * Internal.Exception is called out separately so a regression reads as what it
 * is rather than as twenty missing messages.
 *
 * Line 118 aborts on PHP 8.4 as well, and for a second reason: a key that wraps
 * onto PHP_INT_MIN has no integer negation, so negating it after the wrap hands
 * a float back to the key coercion, which 8.4 reports as `Implicit conversion
 * from float ... to int loses precision` and 8.5 as the same warning the cast
 * raises. Both are diagnostics inside a sniff, and both abort the file here.
 *
 * Runs the real phpcs, and only for this one fixture: the in-process harness
 * cannot see the failure at all, and no cheaper path installs Runner's handler.
 */
it('resolves such a key without aborting the installed run', function (): void {
    $run = installedSniffFixtureRun(DUPLICATED_ARRAY_KEY, 'failing.php');
    $sources = array_column($run['messages'], 'source');

    expect($sources)->not->toContain('Internal.Exception')
        ->and(array_unique($sources))->toBe([DUPLICATED_ARRAY_KEY . '.Found'])
        ->and(array_column($run['messages'], 'line'))->toContain(47, 91, 93, 102, 110, 118, 125);
});

/**
 * A leading zero marks an octal literal only when the digits after it are octal
 * digits and nothing else follows them. The period and the exponent are what
 * make `0.5` and `0e5` decimal, and `05.5` is decimal too even though an octal
 * digit is what follows its zero.
 *
 * The key is asserted and not merely the report, because the two answers this
 * separates are both a key: reading `05.5` as the octal `05` gives 5 as well.
 * What that reading actually does is decline the literal — an out-of-range
 * non-decimal literal is left unresolved, and `05.5` is not out of range, so
 * the decline would be silent — but a resolution that reached 5 by the wrong
 * route would be indistinguishable here without the key.
 */
it('reads a leading zero as octal only when the digits are octal', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[135])->toBe(['Duplicate array key 0 overrides the entry on line 134; remove one of them'])
        ->and($messages[136])->toBe(['Duplicate array key 0 overrides the entry on line 134; remove one of them'])
        ->and($messages[144])->toBe(['Duplicate array key 5 overrides the entry on line 143; remove one of them']);
});

/**
 * The compliant fixture through the shipped binary, where a malformed literal
 * costs the whole file rather than one message.
 *
 * `089` reaches the sniff as a single integer token — PHP_CodeSniffer reads a
 * file that does not compile, and only compilation rejects the digits — and
 * handing them to octdec() raises "Invalid characters passed for attempted
 * conversion". Runner rethrows any diagnostic raised inside a sniff, File
 * records Internal.Exception, and the file's report is replaced by a single
 * "an error occurred during processing". The in-process harness cannot see
 * this: it drives a LocalFile, which never installs Runner's handler, so the
 * compliant fixture stays green there with the digit check removed.
 *
 * Internal.Exception is named rather than left to the empty-list assertion so
 * that a regression reads as the abort it is. The status is asserted alongside
 * it because an abort is reported as an error and exits non-zero.
 */
it('declines a malformed literal without aborting the installed run', function (): void {
    $run = installedSniffFixtureRun(DUPLICATED_ARRAY_KEY, 'passing.php');

    expect(array_column($run['messages'], 'source'))->not->toContain('Internal.Exception')
        ->and($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
});

/**
 * Detection only. Deleting the overridden entry is not a mechanical rewrite:
 * its value can carry a side effect, and which of the two entries is the
 * mistake is a judgement about intent.
 */
it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->not->toContain(true);
});

/**
 * The fixer itself, run over the fixture every violation above comes from, so
 * "unfixable" is measured rather than read off a flag: a sniff that grew a
 * fixer hook by accident would rewrite the file here even while the counts
 * above still looked right.
 */
it('leaves the failing fixture byte-identical under the fixer', function (): void {
    $path = fixturePath(sniffFixtureDirectory(DUPLICATED_ARRAY_KEY), 'failing.php');

    expect(autofixedContents(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')))
        ->toBe(file_get_contents($path));
});

/**
 * The four shapes where this sniff and PHPMD 2.15.0 disagree, all verified by
 * running both tools over this fixture.
 *
 * Lines 8, 10, 11, and 18 are stricter than PHPMD, which compares key literals
 * as source text and so sees `01`, `0x2`, `1.9`, and a negated literal as keys
 * of their own. Line 34 is the scope difference: PHPMD inspects arrays inside
 * methods and functions only, so it says nothing at all about this fixture,
 * while this sniff registers on the literal wherever it appears.
 *
 * Line 26 is the one report PHPMD makes that this sniff does not: stripping
 * the quotes makes `'01'` look like the octal literal `01`, but PHP keeps
 * `'01'` a string key. Its absence from the list below is the assertion.
 */
it('pins where the sniff and PHPMD differ', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        8 => [DUPLICATED_ARRAY_KEY . '.Found'],
        10 => [DUPLICATED_ARRAY_KEY . '.Found'],
        11 => [DUPLICATED_ARRAY_KEY . '.Found'],
        18 => [DUPLICATED_ARRAY_KEY . '.Found'],
        34 => [DUPLICATED_ARRAY_KEY . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * An array left unterminated in a file being edited keeps its T_ARRAY token
 * and its opener but gains no closer, so there are no bounds to walk. The
 * array is abandoned rather than half-read.
 */
it('stays silent on an unterminated array', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'unterminated-array.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The same, for an unterminated construct *inside* an array that does close.
 * This one changes the verdict rather than merely avoiding a lookup: the index
 * `[0, ` holds a comma, so a walk that read past the unterminated bracket
 * would take it for an element separator and report the fixture's `'k' => 2`
 * as a duplicate of its `'k' => 1`.
 */
it('stays silent on an unterminated construct inside an array', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'unterminated-index.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The sniff run over this package's own test source, which is the coverage
 * this rule never had. Every top-level `.php` file under tests/Standards/ has
 * to come back with no duplicate key — including this file and
 * ConvertToCollectionTest.php, whose CONVERT_TO_COLLECTION_REVIEWED_SITES
 * ledger is the array a merge has damaged twice (#356, #369).
 *
 * Unlike that ledger, this guard holds at zero rather than pinning a list of
 * accepted findings: a duplicate top-level key here is a defect to remove or
 * rename, never one to record. It is silent today because there are none —
 * all 96 files come back clean.
 *
 * The target set is a real filesystem scan rather than a written-out list, so
 * a file added to the directory is swept the day it lands. The glob does not
 * recurse, which is what keeps tests/Standards/Fixtures/ out: `*.php` matches
 * no directory. Three assertions stop the scan from passing for the wrong
 * reason — the paths are an array (glob answers false on failure), there are
 * at least 70 of them (96 today, so a scan that collected nothing or rooted
 * itself elsewhere cannot pass quietly), and the two files named above are in
 * the very variable the loop below iterates.
 *
 * The token count is asserted per file, and per file rather than in aggregate,
 * because a file PHP_CodeSniffer cannot open reads as clean: it yields no
 * tokens, so the sniff never runs and no `.Found` violation can be reported.
 * The test underneath measures that rather than asserting it here.
 */
it('leaves the package own test source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/tests/Standards/*.php');

    expect($paths)->toBeArray()
        ->and(count($paths))->toBeGreaterThanOrEqual(70)
        ->and($paths)->toContain(
            cleanCodeRoot() . '/tests/Standards/ConvertToCollectionTest.php',
            cleanCodeRoot() . '/tests/Standards/DuplicatedArrayKeyTest.php',
        );

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([DUPLICATED_ARRAY_KEY], $path);
        $relative = basename($path);

        expect($file->numTokens)->toBeGreaterThan(0, "{$relative} produced no tokens")
            ->and(array_column(violationTuples($file), 'source'))
            ->not->toContain(DUPLICATED_ARRAY_KEY . '.Found');
    }
});

/**
 * What the token-count guard above is for, measured on the one input that
 * trips it. Every file in tests/Standards/ tokenises today, so the sweep alone
 * never reaches the guard and could not show that it discriminates.
 *
 * A path PHP_CodeSniffer cannot open reads as clean twice over: it records
 * Internal.LocalFile rather than anything this sniff reports, so the
 * `.Found` assertion below passes on it, exactly as it would inside the sweep.
 * The token count is the only assertion that tells the two apart.
 */
it('reads no tokens from a path it cannot open', function (): void {
    $file = analyzeWithSniffs([DUPLICATED_ARRAY_KEY], cleanCodeRoot() . '/tests/Standards/no-such-file.php');

    expect($file->numTokens)->toBe(0)
        ->and(violationSourcesByLine($file->getErrors()))->toBe([1 => ['Internal.LocalFile']])
        ->and(array_column(violationTuples($file), 'source'))
        ->not->toContain(DUPLICATED_ARRAY_KEY . '.Found');
});
