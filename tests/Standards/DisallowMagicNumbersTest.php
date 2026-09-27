<?php

declare(strict_types=1);

const MAGIC_NUMBERS = 'CleanCode.Naming.DisallowMagicNumbers';

const MAGIC_NUMBERS_WARNING = MAGIC_NUMBERS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MAGIC_NUMBERS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a bare literal in every use context', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 7, 'column' => 27, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 11, 'column' => 26, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 13, 'column' => 21, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 14, 'column' => 36, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 17, 'column' => 24, 'source' => MAGIC_NUMBERS_WARNING],
        ]);
});

it('names the literal in the warning message', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    expect($warnings[13][21][0]['message'])->toContain('42');
});

it('polices a callable body while exempting its parameter defaults', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'callable-bodies.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('reads every numeric base when matching the ignore list', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'numeric-bases.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        12 => [MAGIC_NUMBERS_WARNING],
        13 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
        15 => [MAGIC_NUMBERS_WARNING],
        16 => [MAGIC_NUMBERS_WARNING],
        17 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('attaches a negating minus to the literal but not a subtracting one', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'negative-numbers.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([
        7 => [MAGIC_NUMBERS_WARNING, MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
    ])
        ->and($warnings[7][15][0]['message'])->toContain('number 100 ')
        ->and($warnings[7][21][0]['message'])->toContain('number 5 ')
        ->and($warnings[8][13][0]['message'])->toContain('number -7 ');
});

it('exposes a configurable ignore list', function (): void {
    expect(violationSourcesByLine(analyzeFixture(MAGIC_NUMBERS, 'configured.php')->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        6 => [MAGIC_NUMBERS_WARNING],
    ]);

    $configured = analyzeFixture(
        MAGIC_NUMBERS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignoredNumbers = ['1000'];
        }
    );

    expect(violationSourcesByLine($configured->getWarnings()))->toBe([
        6 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('reports warnings rather than errors', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(5);
});

it('offers no fixer', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    $fixable = [];

    foreach ($warnings as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                $fixable[] = $message['fixable'];
            }
        }
    }

    expect($fixable)->toBe([false, false, false, false, false]);
});
