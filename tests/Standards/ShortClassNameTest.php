<?php

declare(strict_types=1);

const SHORT_CLASS_NAME = 'CleanCode.Naming.ShortClassName';

const SHORT_CLASS_NAME_ERROR = SHORT_CLASS_NAME . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_CLASS_NAME);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every class-like declaration under the threshold', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 3, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 7, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 11, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 15, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 19, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
        ]);
});

it('names the class and the configured threshold in the message', function (): void {
    $errors = analyzeFixture(SHORT_CLASS_NAME, 'failing.php')->getErrors();

    expect($errors[3][1][0]['message'])
        ->toBe('Avoid classes with short names like Fo. Configured minimum length is 3.');
});

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false, false, false]);
});

it('exposes a configurable minimum', function (): void {
    expect(analyzeFixture(SHORT_CLASS_NAME, 'exceptions.php')->getErrors())->toBe([]);

    $raised = analyzeFixture(
        SHORT_CLASS_NAME,
        'exceptions.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect(violationSourcesByLine($raised->getErrors()))->toBe([
        3 => [SHORT_CLASS_NAME_ERROR],
        7 => [SHORT_CLASS_NAME_ERROR],
        11 => [SHORT_CLASS_NAME_ERROR],
        19 => [SHORT_CLASS_NAME_ERROR],
    ]);
});

it('honors the exceptions list, case-sensitively', function (): void {
    $file = analyzeFixture(
        SHORT_CLASS_NAME,
        'exceptions.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
            $sniff->exceptions = 'Log, URL , FTP';
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        19 => [SHORT_CLASS_NAME_ERROR],
    ]);
});

it('measures a name in bytes, not characters', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'multibyte.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 11, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
        ])
        ->and($file->getErrors()[11][1][0]['message'])
        ->toBe('Avoid classes with short names like Δ. Configured minimum length is 3.');
});

it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
