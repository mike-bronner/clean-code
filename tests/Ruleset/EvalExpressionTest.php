<?php

/**
 * Integration test for the Squiz.PHP.Eval rule as configured in the master
 * CleanCode/ruleset.xml, which replaces PHPMD's Design/EvalExpression rule (issue #107).
 * Fixtures live in tests/fixtures/EvalSniff/.
 *
 * There is no autofixed.php because the rule is not auto-fixable —
 * Squiz\Sniffs\PHP\EvalSniff reports through addWarning() and registers no
 * fixer, so phpcbf cannot act on it, and PHPMD offers no auto-fix for an eval
 * expression either. The fixable-count test below pins that, so the absent
 * autofix fixture stays an asserted fact rather than an assumption.
 *
 * The severity override is pinned here too: the sniff reports a *warning* out
 * of the box and CleanCode/ruleset.xml raises it to an error, so eval() fails a phpcs run
 * the way it fails a phpmd run. That is why the tests assert the reports land
 * in getErrors() and that getWarnings() stays empty.
 */

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

    // Guard against a vacuous pass: an empty report also has zero fixable
    // violations, so pin that the violations are actually there first.
    expect($file->getErrorCount())->toBe(count(EVAL_VIOLATION_LINES))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false]);
});

/**
 * `eval` is a reserved word, but PHP 7.0 onwards allows it as a method name.
 * $object->eval(...), $object?->eval(...) and Class::eval(...) are ordinary
 * method calls, not the language construct, and the tokenizer does not emit
 * T_EVAL for them — so the sniff must stay silent.
 */
it('does not flag methods named eval', function (): void {
    $file = analyzeFixture(EVAL_SNIFF, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
