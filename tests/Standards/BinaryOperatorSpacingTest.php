<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Operators\BinaryOperatorSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;

const BINARY_OPERATOR_SPACING = 'CleanCode.Operators.BinaryOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BINARY_OPERATOR_SPACING);
});

it('replaces its parent rather than running alongside it', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey('Squiz.WhiteSpace.OperatorSpacing');
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BINARY_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(BINARY_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 28, 'source' => BINARY_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 10, 'column' => 28, 'source' => BINARY_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 11, 'column' => 35, 'source' => BINARY_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 12, 'column' => 33, 'source' => BINARY_OPERATOR_SPACING . '.SpacingAfter'],
        ['line' => 17, 'column' => 30, 'source' => BINARY_OPERATOR_SPACING . '.NoSpaceAfter'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(BINARY_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeFixture(BINARY_OPERATOR_SPACING, 'failing.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/../fixtures/BinaryOperatorSpacingSniff/autofixed.php')
    );
});

it('registers exactly the tokens its parent does', function (): void {
    expect((new BinaryOperatorSpacingSniff())->register())
        ->toBe((new OperatorSpacingSniff())->register());
});

it('cedes every context where the passive sniff and its parent disagree', function (): void {
    $divergence = array_diff_key(passiveNonOperandTokens(), squizNonOperandTokens());

    expect($divergence)->not->toBe([])
        ->and(array_diff_key($divergence, BinaryOperatorSpacingSniff::UNARY_SIGN_PRECEDERS))
        ->toBe([]);
});

it('cedes nothing the passive sniff does not claim', function (): void {
    expect(array_diff_key(
        BinaryOperatorSpacingSniff::UNARY_SIGN_PRECEDERS,
        passiveNonOperandTokens()
    ))->toBe([]);
});

it('claims every context its binary counterpart already treats as a non-operand', function (): void {
    expect(array_diff_key(squizNonOperandTokens(), passiveNonOperandTokens()))->toBe([]);
});
