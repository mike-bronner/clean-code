<?php

/**
 * Tests the custom CleanCode.DeadCode.UnusedPrivateElements sniff (No Dead
 * Code, #29), and the construct-coverage and method-versus-property precision
 * work that followed it (#206). Fixtures live in
 * tests/fixtures/UnusedPrivateElementsSniff/ and follow the fixture contract:
 * passing.php is clean, failing.php carries every violation shape. There is no
 * autofixed.php — the sniff is detection-only, because deleting a member is
 * not a rewrite a fixer can make safely.
 *
 * tests/Ruleset/NoDeadCodeRulesetTest.php remains the record of how rules.xml
 * wires this sniff alongside the three third-party sniffs the No Dead Code
 * standard also needs. This file owns the sniff's own behaviour.
 *
 * Every expectation below was cross-checked by mutation: reverting register()
 * to [T_CLASS] drops the enum and anonymous-class findings, and collapsing the
 * two usage maps back into one drops both shared-name findings. Dropping the
 * anonymous-class skip from collectUsedNames() drops the two host-collision
 * findings. The compliant fixture discriminates in the same way — adding
 * T_TRAIT to register(), forcing the call lookahead to a constant, or moving
 * that skip's start from the opening brace to the `new class` token (which
 * swallows the constructor arguments), each makes it report.
 */

declare(strict_types=1);

const UNUSED_PRIVATE_ELEMENTS = 'CleanCode.DeadCode.UnusedPrivateElements';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_PRIVATE_ELEMENTS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The whole violation set, pinned to exact lines and columns so that a
 * regression which flags one more member — a used one — fails here rather than
 * hiding behind a presence-only assertion.
 */
it('flags every dead private member and nothing else', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 22, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 41, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 53, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 78, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 93, 'column' => 28, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 100, 'column' => 30, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 117, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 136, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
    ]);
});

/**
 * The construct-coverage half of #206, named one construct at a time so a
 * narrowing of register() says which surface it lost. Enums declare no
 * properties — PHP forbids enum state — so the enum row is a method.
 */
it('reaches dead members in the class-like constructs it registers', function (int $line, string $code): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey($line)
        ->and($lines[$line])->toBe(UNUSED_PRIVATE_ELEMENTS . '.' . $code);
})->with([
    'named class property' => [13, 'UnusedProperty'],
    'named class method' => [22, 'UnusedMethod'],
    'enum method' => [78, 'UnusedMethod'],
    'anonymous class property' => [93, 'UnusedProperty'],
    'anonymous class method' => [100, 'UnusedMethod'],
]);

/**
 * A trait's private member is flattened into every consuming class and may be
 * used only there, so the trait body cannot prove it dead. UnscannedTrait in
 * passing.php references neither of its private members and must still be
 * silent — the exclusion is deliberate, not an oversight, and this pins it.
 */
it('leaves a trait body alone even when nothing in it uses its private members', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect(violationTuples($file))->toBe([])
        ->and(file_get_contents(fixturePath('UnusedPrivateElementsSniff', 'passing.php')))
        ->toContain('private function secretHelper');
});

/**
 * An anonymous class is a separate class, and PHP denies it access to the
 * enclosing class's private members, so a mention inside its body says nothing
 * about a same-named member of the enclosing one. `HostsCollidingAnonymousClass`
 * uses `$tag` and `shared()` only inside the nested body; both enclosing
 * members are dead and must still be reported. Sharing one usage map across
 * the boundary hid both.
 */
it('does not let a nested anonymous class excuse a same-named host member', function (int $line, string $code): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey($line)
        ->and($lines[$line])->toBe(UNUSED_PRIVATE_ELEMENTS . '.' . $code);
})->with([
    'host property read only inside the nested body' => [117, 'UnusedProperty'],
    'host method called only inside the nested body' => [136, 'UnusedMethod'],
]);

/**
 * Only the anonymous class's *body* is skipped. Its constructor arguments are
 * evaluated in the enclosing scope, so `new class ($this->config)` in
 * `PassesPrivateStateToAnonymousClass` is the sole read that keeps that
 * property alive. Skipping from the `new class` token instead of the opening
 * brace would swallow the argument list and report it dead.
 */
it('still counts a usage in the arguments of a nested anonymous class', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect(violationTuples($file))->toBe([])
        ->and(file_get_contents(fixturePath('UnusedPrivateElementsSniff', 'passing.php')))
        ->toContain('new class ($this->config)');
});

/**
 * PHP keeps property and method names in separate namespaces, so a mention
 * through one syntax says nothing about the other. `$this->foo` leaves the
 * same-named method dead; `$this->bar()` leaves the same-named property dead.
 * Before the two usage maps were split, one shared map let either mention mark
 * both used and neither was reported.
 */
it('does not let a property read excuse a same-named method, or the reverse', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey(41)
        ->and($lines[41])->toBe(UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod')
        ->and($lines)->toHaveKey(53)
        ->and($lines[53])->toBe(UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty');
});
