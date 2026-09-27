<?php

declare(strict_types=1);

const GOTO_SNIFF = 'Generic.PHP.DiscourageGoto';

const GOTO_MESSAGE = 'Use of the goto construct is disallowed; replace it with standard '
    . 'control structures and separate methods.';

const GOTO_VIOLATION_POSITIONS = [
    11 => 1,
    15 => 5,
    24 => 9,
    27 => 5,
    37 => 17,
    41 => 9,
    48 => 13,
    52 => 13,
    55 => 9,
];

const GOTO_LINES_PHPMD_ALSO_REPORTS = [24, 37, 48, 52];

const GOTO_STATEMENT_LINES = [15, 24, 37, 48, 52];

const GOTO_LABEL_LINES = [11, 27, 41, 55];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(GOTO_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every goto statement and target label at its own line and column', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    expect(array_keys($errors))->toBe(array_keys(GOTO_VIOLATION_POSITIONS));

    foreach (GOTO_VIOLATION_POSITIONS as $line => $column) {
        expect(array_keys($errors[$line]))->toBe([$column]);

        $lineErrors = $errors[$line][$column];

        expect($lineErrors)->toHaveCount(1)
            ->and($lineErrors[0]['source'])->toBe(GOTO_SNIFF . '.Found');
    }
});

it('splits its reported lines into goto statements and labels with nothing left over', function (): void {
    $reported = array_keys(GOTO_VIOLATION_POSITIONS);
    $split = array_merge(GOTO_STATEMENT_LINES, GOTO_LABEL_LINES);

    sort($split);

    expect($split)->toBe($reported)
        ->and(array_intersect(GOTO_STATEMENT_LINES, GOTO_LABEL_LINES))->toBe([]);
});

it('reports goto usage as errors rather than warnings', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(GOTO_VIOLATION_POSITIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('replaces the stock discouraged wording with the disallowed wording', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    $messages = [];

    foreach ($errors as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $messages[] = $violation['message'];
            }
        }
    }

    expect($messages)->toHaveCount(count(GOTO_VIOLATION_POSITIONS))
        ->and(array_unique($messages))->toBe([GOTO_MESSAGE])
        ->and($messages)->not->toContain('Use of the GOTO language construct is discouraged');
});

it('reports goto usage without offering an auto-fix', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(GOTO_VIOLATION_POSITIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))
        ->toBe(array_fill(0, count(GOTO_VIOLATION_POSITIONS), false));
});

it('covers every line PHPMD itself reports', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    foreach (GOTO_LINES_PHPMD_ALSO_REPORTS as $line) {
        expect($errors)->toHaveKey($line);
    }
});

it('flags target labels, which PHPMD does not', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    foreach (GOTO_LABEL_LINES as $line) {
        expect($errors)->toHaveKey($line)
            ->and(GOTO_LINES_PHPMD_ALSO_REPORTS)->not->toContain($line);
    }
});

it('flags a goto at file scope, which PHPMD does not', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    expect($errors)->toHaveKey(15)
        ->and(GOTO_LINES_PHPMD_ALSO_REPORTS)->not->toContain(15);
});

it('does not flag goto lookalikes', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
