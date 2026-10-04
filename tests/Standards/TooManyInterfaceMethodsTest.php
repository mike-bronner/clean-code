<?php

declare(strict_types=1);

const TOO_MANY_INTERFACE_METHODS = 'CleanCode.Pattern.TooManyInterfaceMethods';

const TOO_MANY_INTERFACE_METHODS_WARNING = TOO_MANY_INTERFACE_METHODS . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_INTERFACE_METHODS);
});

it('carries the shipped threshold through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->ruleset[TOO_MANY_INTERFACE_METHODS]['properties']['maxMethods'] ?? null)
        ->toBe(['value' => '5', 'scope' => 'sniff']);

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TOO_MANY_INTERFACE_METHODS]];

    expect($sniff->maxMethods)->toBe(5);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags an interface one signature past the maximum on its declaration', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ])->and($file->getErrors())->toBe([]);
});

it('names the interface, the counts and the principle in the message', function (): void {
    $warnings = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php')->getWarnings();

    expect($warnings[11][1][0]['message'])
        ->toContain('OneOverTheMaximum')
        ->toContain('6')
        ->toContain('5')
        ->toContain('Interface Segregation')
        ->toContain('split it into narrower interfaces');
});

it('counts only the signatures the interface body declares', function (): void {
    $file = analyzeFixture(
            TOO_MANY_INTERFACE_METHODS,
            'passing.php',
            static function (object $sniff): void {
                $sniff->maxMethods = 0;
            }
        );

    expect(warningTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);

    $warnings = $file->getWarnings();

    expect($warnings[14][1][0]['message'])->toContain('declares 5 method signatures')
        ->and($warnings[31][1][0]['message'])->toContain('declares 5 method signatures');
});

it('exposes a configurable maximum', function (): void {
    expect(warningTuples(analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'configured.php')))->toBe([
        ['line' => 11, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);

    $raised = analyzeFixture(
            TOO_MANY_INTERFACE_METHODS,
            'configured.php',
            static function (object $sniff): void {
                $sniff->maxMethods = 6;
            }
        );

    expect($raised->getWarnings())->toBe([]);

    $lowered = analyzeFixture(
            TOO_MANY_INTERFACE_METHODS,
            'passing.php',
            static function (object $sniff): void {
                $sniff->maxMethods = 4;
            }
        );

    expect(warningTuples($lowered))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);
});

it('takes the threshold from a ruleset property', function (): void {
    $raised = analyzeFixtureWithRulesetProperties(
            TOO_MANY_INTERFACE_METHODS,
            'configured.php',
            ['maxMethods' => '6']
        );

    expect($raised->getWarnings())->toBe([]);

    $lowered = analyzeFixtureWithRulesetProperties(
            TOO_MANY_INTERFACE_METHODS,
            'passing.php',
            ['maxMethods' => '4']
        );

    expect(warningTuples($lowered))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);
});

it('passes over a half-written interface', function (string $fixture): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['truncated.php', 'nameless.php']);

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
