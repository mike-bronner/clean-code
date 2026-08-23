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
 * escaped and interpolated double-quoted strings, a negated string, and a
 * non-finite float. Each one is an early return, so the fixture's silence is a
 * verdict about them rather than the absence of anything to look at.
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
 * make them the same keys as their neighbours.
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
 * PHP would not agree with. Only the wrap gives exactly these two reports with
 * exactly these two keys, and the keys are the ones the interpreter produces:
 * `(int) 1.0e30` is 5076964154930102272 on PHP 8.1, 8.4 and 8.5 alike, and the
 * arithmetic that replaced that cast was checked against it over 200,000
 * random magnitudes on 8.4 and 8.5 with no disagreement.
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
        ->and($messages)->not->toHaveKey(90);
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
 * Runs the real phpcs, and only for this one fixture: the in-process harness
 * cannot see the failure at all, and no cheaper path installs Runner's handler.
 */
it('resolves such a key without aborting the installed run', function (): void {
    $run = installedSniffFixtureRun(DUPLICATED_ARRAY_KEY, 'failing.php');
    $sources = array_column($run['messages'], 'source');

    expect($sources)->not->toContain('Internal.Exception')
        ->and(array_unique($sources))->toBe([DUPLICATED_ARRAY_KEY . '.Found'])
        ->and(array_column($run['messages'], 'line'))->toContain(91, 93);
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
