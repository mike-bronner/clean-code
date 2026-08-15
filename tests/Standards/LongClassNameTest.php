<?php

/**
 * Tests the custom CleanCode.Naming.LongClassName sniff, which replicates
 * PHPMD's Naming/LongClassName rule (docs/phpmd/naming-longclassname.md).
 *
 * Every expectation below was checked against a live PHPMD 2.15.0 run over the
 * very same three fixtures, with the same properties: `passing.php` produces no
 * PHPMD violation, `failing.php` produces one per declaration at the lines
 * pinned here, and `subtraction.php` produces six with default properties and
 * exactly two once the prefix/suffix lists are configured. The point of the
 * sniff is parity, so the fixtures are shaped to be runnable by both tools.
 *
 * The rule is detection-only — renaming a type means rewriting every reference
 * to it, which a single-file fixer cannot do — so there is no autofixed
 * fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const LONG_CLASS_NAME = 'CleanCode.Naming.LongClassName';

/**
 * Prefix and suffix lists for subtraction.php. `MockRepository` sits *after*
 * `Repository` deliberately: PHPMD stops at the first matching suffix, so the
 * longer entry must never win.
 */
const LONG_CLASS_NAME_SUBTRACTIONS = [
    'subtractPrefixes' => 'Abstract',
    'subtractSuffixes' => 'Repository,Factory,MockRepository',
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LONG_CLASS_NAME);
});

/**
 * passing.php is the silent half of the boundary and the sniff's near-miss
 * inventory at once. It declares a class, an interface, a trait, and an enum
 * whose names are each exactly 40 bytes — the default maximum, which PHPMD
 * reports above rather than at — and surrounds them with the shapes the sniff
 * must not measure: an anonymous class, a 50-byte method name, a 48-byte
 * property name, an import and an instantiation of a 41-byte type declared
 * elsewhere, and a class whose namespace makes its fully-qualified name long
 * while its declared name stays short.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The flagged half of the boundary: the same four declaration keywords, each
 * one byte over the maximum, plus a 47-byte name well clear of it. Columns are
 * asserted alongside lines because the sniff reports at the declaration
 * keyword, not at the name — `final class` therefore reports at column 7.
 */
it('flags every over-length declaration at its keyword', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 10, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 14, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 19, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 26, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 31, 'column' => 7, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The message carries the offending name, its measured length, and the
 * threshold, so a report says why the name failed rather than only that it
 * did. Pinned on the shortest over-length name in the fixture (41 bytes
 * against a maximum of 40) — the one where an off-by-one in the measurement
 * would be least visible.
 */
it('names the type, its length, and the threshold', function (): void {
    $errors = analyzeFixture(LONG_CLASS_NAME, 'failing.php')->getErrors();

    expect($errors[10][1][0]['message'])
        ->toBe('Name CustomerAddressBookSynchronizationHandler is 41 characters long; keep it to 40 or fewer');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * A truncated `class` keyword with no name after it reaches the sniff — PHPCS
 * still emits the T_CLASS token — and `getDeclarationName()` returns null for
 * it. There is no name to measure, so the sniff says nothing rather than
 * guessing a length. Without the null guard the sniff fatals on the fixture.
 */
it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The threshold is read from the property rather than hardcoded. Lowering the
 * maximum to 39 flags exactly the four 40-byte declarations in passing.php and
 * nothing else — the anonymous class, the long method and property names, the
 * imported and instantiated 41-byte type, and the short-named class in the long
 * namespace all stay silent, so this doubles as proof that the fixture's near
 * misses are silent because of *what* they are and not merely how long.
 */
it('reads the maximum from its property', function (): void {
    $file = analyzeFixture(
        LONG_CLASS_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->maximum = 39;
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        14 => ['CleanCode.Naming.LongClassName.TooLong'],
        49 => ['CleanCode.Naming.LongClassName.TooLong'],
        54 => ['CleanCode.Naming.LongClassName.TooLong'],
        62 => ['CleanCode.Naming.LongClassName.TooLong'],
    ]);
});

/**
 * Raising the maximum to 41 silences the four 41-byte declarations in
 * failing.php and leaves only the 47-byte one. Together with the test above,
 * this pins the comparison as "strictly greater than the maximum" from both
 * sides: at 41 the 41-byte names pass, at 40 they fail.
 */
it('stays silent at exactly the maximum', function (): void {
    $file = analyzeFixture(
        LONG_CLASS_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->maximum = 41;
        }
    );

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([31 => ['CleanCode.Naming.LongClassName.TooLong']]);
});

/**
 * The unconfigured control for subtraction.php: with the default empty prefix
 * and suffix lists, every one of its six declarations is over the maximum. The
 * configured run below has to be read against this — without it, a sniff that
 * had simply fallen silent on the fixture would look like working subtraction.
 */
it('flags every name in the subtraction fixture without the lists configured', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'subtraction.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([13, 18, 24, 30, 37, 43]);
});

/**
 * Configured, four of those six fall to 36 bytes and go silent: line 13 by the
 * prefix, 18 by the first suffix in the list, 24 by the second (so the whole
 * list is read, not just its head), and 30 by both at once.
 *
 * The two that remain are the point of the test. Line 37 ends in
 * `MockRepository`, but `Repository` matches first and PHPMD stops there — 10
 * bytes come off rather than 14, leaving 41. A subtraction that preferred the
 * longest match, or that did not stop at the first, would silence it. Line 43
 * has both a matching prefix and a matching suffix and is still 41 bytes after
 * both: subtraction shortens a name, it does not exempt one.
 */
it('subtracts one prefix and one suffix, each the first that matches', function (): void {
    $file = analyzeFixture(
        LONG_CLASS_NAME,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractSuffixes'];
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        37 => ['CleanCode.Naming.LongClassName.TooLong'],
        43 => ['CleanCode.Naming.LongClassName.TooLong'],
    ]);
});

/**
 * The prefix list stops at its first match too. `AbstractWarehouse` sits after
 * `Abstract` and both match line 43, so 8 bytes come off rather than 25,
 * leaving 51 — still over the maximum. A loop that carried on past the first
 * match would subtract both and silence the line.
 *
 * This is the prefix-side twin of the `Repository` / `MockRepository` case
 * above. The two loops are separate, so each needs its own case: pinning one
 * says nothing about the other.
 */
it('stops at the first matching prefix', function (): void {
    $file = analyzeFixture(
        LONG_CLASS_NAME,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = 'Abstract,AbstractWarehouse';
        }
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([18, 24, 30, 37, 43]);
});

/**
 * Both remaining reports state the *subtracted* length, not the raw one: 51
 * and 59 bytes respectively, each measured as 41 once its prefix and suffix
 * come off. A sniff that subtracted correctly for the comparison but reported
 * the raw length would pass the test above and fail here.
 */
it('reports the subtracted length', function (): void {
    $errors = analyzeFixture(
        LONG_CLASS_NAME,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractSuffixes'];
        }
    )->getErrors();

    expect($errors[37][1][0]['message'])
        ->toBe('Name WarehouseInventoryReplenishmentAuditsMockRepository is 41 characters long; keep it to 40 or fewer')
        ->and($errors[43][10][0]['message'])
        ->toBe(
            'Name AbstractWarehouseInventoryReplenishmentAuditTrailRepository'
            . ' is 41 characters long; keep it to 40 or fewer'
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
 * Configured with padding and stray commas around a single `Abstract`, line 13
 * still loses its prefix and goes silent while the other five stay flagged.
 * Drop the trim and `Abstract` no longer matches; drop the empty-entry filter
 * and the empty prefix wins the race — either way line 13 comes back.
 */
it('trims list entries and drops empty ones', function (): void {
    $file = analyzeFixture(
        LONG_CLASS_NAME,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = ' , Abstract , ';
        }
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([18, 24, 30, 37, 43]);
});
