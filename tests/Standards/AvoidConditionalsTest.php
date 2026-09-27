<?php

declare(strict_types=1);

const AVOID_CONDITIONALS = 'CleanCode.Conditionals.AvoidConditionals';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_CONDITIONALS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns once per conditional construct at its own line and column', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 9, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 20, 'column' => 11, 'source' => AVOID_CONDITIONALS . '.ElseIfStatement'],
        ['line' => 29, 'column' => 28, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 34, 'column' => 9, 'source' => AVOID_CONDITIONALS . '.SwitchStatement'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns on every variant shape of the four constructs', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 25, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 27, 'column' => 8, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 32, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 34, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.ElseIfStatement'],
        ['line' => 39, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.SwitchStatement'],
        ['line' => 47, 'column' => 20, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 50, 'column' => 23, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 50, 'column' => 47, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 53, 'column' => 36, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 56, 'column' => 48, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
    ]);
});

it('reports a switch once, not once per case', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'shapes.php');

    $switchWarnings = array_filter(
        warningTuples($file),
        static fn (array $violation): bool => $violation['source'] === AVOID_CONDITIONALS . '.SwitchStatement'
    );

    expect($switchWarnings)->toHaveCount(1);
});
