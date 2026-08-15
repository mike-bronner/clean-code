<?php

/**
 * Tests the custom CleanCode.Operators.BinaryOperatorSpacing sniff — the
 * Squiz.WhiteSpace.OperatorSpacing subclass the master ruleset wires in for
 * Arrays: Operator spacing & line breaks (#35), with its unary-sign detection
 * corrected so Operators: Passive (#64) can own unary signs outright.
 *
 * Fixtures live in tests/fixtures/BinaryOperatorSpacingSniff/ and follow the
 * three-fixture contract. passing.php is deliberately discriminating: besides
 * correctly-spaced binary operators it carries all four ceded unary contexts,
 * which this sniff must stay silent on.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Operators\BinaryOperatorSpacingSniff;
use MikeBronner\CleanCode\Sniffs\WhiteSpace\PassiveOperatorSpacingSniff;
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

/**
 * All four inherited message codes, one per line. Keeping the parent's codes
 * is what makes the swap in rules.xml invisible to anything that references
 * them; a renamed code would show up here.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(BINARY_OPERATOR_SPACING, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        9 => [BINARY_OPERATOR_SPACING . '.NoSpaceBefore'],
        10 => [BINARY_OPERATOR_SPACING . '.NoSpaceAfter'],
        11 => [BINARY_OPERATOR_SPACING . '.SpacingBefore'],
        12 => [BINARY_OPERATOR_SPACING . '.SpacingAfter'],
        17 => [BINARY_OPERATOR_SPACING . '.NoSpaceAfter'],
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

/**
 * The heart of this sniff, and the reason it exists.
 *
 * Operators: Passive requires a unary sign flush against its operand; Arrays:
 * Operator spacing requires a space around a binary one. Both are enforced by
 * fixers, so any token the passive sniff calls "unary" while the parent calls
 * it "binary" is a file phpcbf can never settle: one fixer strips the space,
 * the other puts it back, and phpcbf abandons the whole file.
 *
 * Rather than list those contexts by hand in two places and hope they stay in
 * step, this derives the divergence from the two real classes and asserts this
 * sniff cedes every token in it. Add a context to the passive sniff without
 * ceding it here and this fails — which is the point: the collision is closed
 * as a class, not one surface at a time.
 */
it('cedes every context where the passive sniff and its parent disagree', function (): void {
    $parent = new OperatorSpacingSniff();
    $parent->register();

    $passive = new PassiveOperatorSpacingSniff();
    $reflected = new ReflectionMethod($passive, 'nonOperandTokens');
    $reflected->setAccessible(true);

    $divergence = array_diff_key(
        $reflected->invoke($passive),
        (function () use ($parent): array {
            $property = new ReflectionProperty($parent, 'nonOperandTokens');
            $property->setAccessible(true);

            return $property->getValue($parent) ?? [];
        })()
    );

    expect($divergence)->not->toBe([])
        ->and(array_diff_key($divergence, BinaryOperatorSpacingSniff::UNARY_SIGN_PRECEDERS))
        ->toBe([]);
});

/**
 * The other direction of the same contract: nothing is ceded that the passive
 * sniff does not actually claim, or a spaced binary sign would go unreported
 * by both sniffs and the standard would have a hole in it.
 */
it('cedes nothing the passive sniff does not claim', function (): void {
    $passive = new PassiveOperatorSpacingSniff();
    $reflected = new ReflectionMethod($passive, 'nonOperandTokens');
    $reflected->setAccessible(true);

    expect(array_diff_key(
        BinaryOperatorSpacingSniff::UNARY_SIGN_PRECEDERS,
        $reflected->invoke($passive)
    ))->toBe([]);
});
