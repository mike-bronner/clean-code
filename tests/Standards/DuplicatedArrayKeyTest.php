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
            [65, 9], [67, 5], [72, 17], [79, 5], [81, 5],
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
