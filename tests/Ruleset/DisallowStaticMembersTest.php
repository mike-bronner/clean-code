<?php

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

it('flags static methods in every object-oriented container', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'containers.php');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 17, 'column' => 21, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 22, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
        ['line' => 31, 'column' => 12, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
    ]);
});

it('skips a member an ancestor declares static, and reports every other', function (): void {
    $file = analyzeFixture(DISALLOW_STATIC_MEMBERS, 'inherited-static.php');

    expect(violationTuples($file))->toBe([
        ['line' => 27, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticProperty'],
        ['line' => 30, 'column' => 13, 'source' => DISALLOW_STATIC_MEMBERS . '.StaticMethod'],
    ]);
});

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
