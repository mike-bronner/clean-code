<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Operators\BooleanOperatorSpacingSniff;
use MikeBronner\CleanCode\Sniffs\Operators\NotOperatorSpacingSniff;
use PHP_CodeSniffer\Standards\PSR12\Sniffs\Operators\OperatorSpacingSniff as Psr12OperatorSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Strings\ConcatenationSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\LogicalOperatorSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;

const BOOLEAN_OPERATOR_SPACING = 'CleanCode.Operators.BooleanOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 17, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 18, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 18, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 19, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 19, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 21, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 22, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 23, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 23, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 24, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 24, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 26, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 27, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 28, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 28, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 29, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 29, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 31, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 32, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 33, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 33, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 34, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 34, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 36, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 37, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 38, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 38, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 39, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 39, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(30)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('registers exactly the five boolean operators', function (): void {
    expect((new BooleanOperatorSpacingSniff())->register())
        ->toBe([
            T_BOOLEAN_AND => T_BOOLEAN_AND,
            T_BOOLEAN_OR => T_BOOLEAN_OR,
            T_LOGICAL_AND => T_LOGICAL_AND,
            T_LOGICAL_OR => T_LOGICAL_OR,
            T_LOGICAL_XOR => T_LOGICAL_XOR,
        ]);
});

it('shares no registered token with the sniffs that own the rest of the standard', function (
    string $sniffClass
): void {
    $ours = (new BooleanOperatorSpacingSniff())->register();
    $theirs = (new $sniffClass())->register();

    expect(array_intersect(array_values($ours), array_values($theirs)))->toBe([]);
})->with([
    'assignment operators — Squiz.WhiteSpace.OperatorSpacing' => OperatorSpacingSniff::class,
    'concatenation — Squiz.Strings.ConcatenationSpacing' => ConcatenationSpacingSniff::class,
    'logical not — CleanCode.Operators.NotOperatorSpacing' => NotOperatorSpacingSniff::class,
]);

it('keeps the spacing sniffs that do overlap out of the master ruleset', function (
    string $sniffCode,
    string $sniffClass
): void {
    $ours = array_values((new BooleanOperatorSpacingSniff())->register());
    $theirs = array_values((new $sniffClass())->register());

    expect(array_values(array_intersect($ours, $theirs)))->toBe($ours);

    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey($sniffCode);
})->with([
    'PSR12.Operators.OperatorSpacing — excluded' => [
        'PSR12.Operators.OperatorSpacing',
        Psr12OperatorSpacingSniff::class,
    ],
    'Squiz.WhiteSpace.LogicalOperatorSpacing — never referenced' => [
        'Squiz.WhiteSpace.LogicalOperatorSpacing',
        LogicalOperatorSpacingSniff::class,
    ],
]);

it('shares these tokens in the master ruleset only with sniffs reporting another concern', function (): void {
    [, $ruleset] = buildRuleset();

    $ours = array_values((new BooleanOperatorSpacingSniff())->register());

    $sharing = array_keys(array_filter(
            $ruleset->sniffCodes,
            static fn (string $class): bool => array_intersect(
                    array_values((array) $ruleset->sniffs[$class]->register()),
                    $ours
                ) !== []
        ));

    sort($sharing);

    expect($sharing)->toBe([
        BOOLEAN_OPERATOR_SPACING,
        'CleanCode.Operators.OperatorLineBreak',
        'Generic.PHP.LowerCaseKeyword',
    ]);
});
