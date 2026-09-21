<?php

/**
 * Integration test for the custom CleanCode.Classes.DisallowStaticMembers
 * sniff as wired into the master CleanCode/ruleset.xml (Classes: No Statics, issue #19).
 * Fixtures live in tests/fixtures/DisallowStaticMembersSniff/.
 *
 * The sniff is detection-only, so there is no autofixed fixture — instead the
 * tests prove every reported violation is non-fixable.
 */

declare(strict_types=1);

const DISALLOW_STATIC_MEMBERS = 'CleanCode.Classes.DisallowStaticMembers';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_STATIC_MEMBERS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags static methods and properties at their own line and column', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticProperty'],
        ['line' => 11, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 21, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 25, 'column' => 15, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticProperty'],
    ]);
});

/**
 * interface (12), abstract class (17), trait (22), enum (31). Line 17 also has
 * a `static` return type on the same line — only the modifier (column 21) is
 * flagged, proving return types are not caught.
 */
it('flags static methods in every object-oriented container', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'containers.php');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 17, 'column' => 21, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 22, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 31, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
    ]);
});

/**
 * The skip PHP forces. Dropping `static` from a member an ancestor declares
 * static is a fatal — "Cannot make static method Base::x() non static" for a
 * method, "Cannot redeclare static Base::$x as non static" for a property — so
 * reporting those lines would be asking for code that does not load. Laravel's
 * Facade is the shape that reaches consumers: getFacadeAccessor() is abstract
 * protected static, so every facade written against it must keep the keyword.
 *
 * The fixture extends PHP_CodeSniffer\Util\Common rather than a fixture parent
 * on purpose. A fixture class is not autoloadable, so class_exists() is false
 * for it and the sniff would take its unresolvable-ancestor path — the test
 * would pass while exercising the wrong branch.
 *
 * Non-vacuous by mutation: making overridesStaticMethod() return true always
 * drops line 30, and returning false always adds line 21; the same two
 * mutations on redeclaresStaticProperty() move lines 27 and 16.
 *
 * The sibling test below covers the visibility half of both guards.
 */
it('skips a member an ancestor declares static, and reports every other', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'inherited-static.php');

    expect(violationTuples($file))->toBe([
        ['line' => 27, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticProperty'],
        ['line' => 30, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
    ]);
});

/**
 * A private ancestor member is not inherited, so PHP loads a non-static
 * redeclaration of one without complaint. Skipping on a private ancestor would
 * hide a real violation, which is why the guard tests visibility as well as
 * staticness.
 */
it('still reports a member whose only static ancestor is private', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'private-ancestor.php');

    expect(violationTuples($file))->toBe([
        ['line' => 17, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticProperty'],
        ['line' => 23, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
    ]);
});

it('reports violations that are not auto-fixable', function (string $fixture): void {
    $flags = violationFixableFlags(analyzeFixture(DISALLOW_STATIC_MEMBERS, $fixture));

    expect($flags)->not->toBeEmpty()
        ->and($flags)->each->toBeFalse();
})->with(['failing.php', 'containers.php', 'inherited-static.php']);
