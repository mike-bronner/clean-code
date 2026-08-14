<?php

/**
 * Tests the "Conditionals: No Inline If-Statements" standard (#9), enforced by
 * Generic.ControlStructures.InlineControlStructure.
 *
 * PSR12 (referenced in rules.xml) already bundles this sniff, so it is active
 * in the master ruleset regardless; rules.xml also references it explicitly as
 * belt-and-suspenders (CONTRIBUTING — third-party rules a standard depends on
 * are wired in by name). The registration test therefore proves the sniff is
 * reachable through rules.xml (via either route), not that the explicit ref
 * alone is load-bearing. The behaviour tests pin the sniff in isolation against
 * the fixture, so they stay stable as sibling standards land in the shared
 * ruleset.
 *
 * Fixtures live in tests/fixtures/InlineControlStructureSniff/: passing.php,
 * failing.php, and the expected auto-fixed output autofixed.php. passing.php
 * carries a braced form of every structure the sniff registers on — if/else,
 * elseif chains, for, foreach, while, do-while, switch, and nesting — so its
 * silence is a verdict about each of them rather than about an absence of
 * control structures.
 */

declare(strict_types=1);

const INLINE_CONTROL_STRUCTURE = 'Generic.ControlStructures.InlineControlStructure';

it('is reachable through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(INLINE_CONTROL_STRUCTURE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags inline conditionals at the expected lines', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toBe([
        25 => 1,
        28 => 1,
        29 => 1,
        32 => 1,
        33 => 1,
        34 => 1,
        37 => 2,
        41 => 1,
        46 => 1,
    ]);
});

it('marks every violation auto-fixable', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('adds braces when fixed', function (): void {
    $file = analyzeFixture(INLINE_CONTROL_STRUCTURE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('InlineControlStructureSniff', 'autofixed.php')));
});
