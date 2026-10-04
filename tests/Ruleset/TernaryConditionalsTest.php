<?php

declare(strict_types=1);

const TERNARY_CONDITIONALS_SNIFFS = [
    'CleanCode.Conditionals.DisallowNestedTernary',
    'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator',
];

const REQUIRE_TERNARY_SNIFF = 'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator';

const TERNARY_NOT_USED = REQUIRE_TERNARY_SNIFF . '.TernaryOperatorNotUsed';

const NESTED_TERNARY_VIOLATION = 'CleanCode.Conditionals.DisallowNestedTernary.NestedTernary';

const TERNARY_CONDITIONALS_VIOLATIONS = [
    ['line' => 4, 'column' => 1, 'source' => TERNARY_NOT_USED],
    ['line' => 13, 'column' => 5, 'source' => TERNARY_NOT_USED],
    ['line' => 21, 'column' => 34, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 24, 'column' => 32, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 28, 'column' => 34, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 31, 'column' => 33, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 35, 'column' => 1, 'source' => TERNARY_NOT_USED],
    ['line' => 44, 'column' => 1, 'source' => TERNARY_NOT_USED],
];

const TERNARY_CONDITIONALS_FIXABLE = [true, true, false, false, false, false, false, false];

it('wires the require-ternary sniff into the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_TERNARY_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'compliant.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports both halves of the standard through the master ruleset', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'violations.php');

    expect(violationTuples($file))->toBe(TERNARY_CONDITIONALS_VIOLATIONS)
        ->and($file->getWarnings())->toBe([]);
});

it('offers an auto-fix for the convertible if/else only', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'violations.php');

    expect(violationFixableFlags($file))->toBe(TERNARY_CONDITIONALS_FIXABLE)
        ->and($file->getFixableCount())->toBe(2);
});

it('has no Slevomat sniff covering nested ternaries', function (): void {
    $file = analyzeWithStandard(
            'SlevomatCodingStandard',
            fixturePath('DisallowNestedTernarySniff', 'failing.php')
        );

    expect($file->ruleset->sniffCodes)
        ->not->toHaveKey('SlevomatCodingStandard.ControlStructures.DisallowNestedTernaryOperator')
        ->and($file->ruleset->sniffCodes)
        ->toHaveKey('SlevomatCodingStandard.ControlStructures.DisallowShortTernaryOperator');
});
