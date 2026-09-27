<?php

declare(strict_types=1);

const EVAL_SNIFF = 'Squiz.PHP.Eval';

const EVAL_VIOLATION_LINES = [12, 20, 25];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EVAL_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EVAL_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each eval expression at its own line', function (): void {
    $errors = analyzeFixture(EVAL_SNIFF, 'failing.php')->getErrors();

    expect(array_keys($errors))->toBe(EVAL_VIOLATION_LINES);

    foreach (EVAL_VIOLATION_LINES as $line) {
        $lineErrors = array_merge(...array_values($errors[$line]));

        expect($lineErrors)->toHaveCount(1)
            ->and($lineErrors[0]['source'])->toBe(EVAL_SNIFF . '.Discouraged');
    }
});

it('reports eval expressions as errors rather than warnings', function (): void {
    $file = analyzeFixture(EVAL_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(EVAL_VIOLATION_LINES))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('reports eval expressions without offering an auto-fix', function (): void {
    $file = analyzeFixture(EVAL_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(EVAL_VIOLATION_LINES))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false]);
});

it('does not flag methods named eval', function (): void {
    $file = analyzeFixture(EVAL_SNIFF, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
