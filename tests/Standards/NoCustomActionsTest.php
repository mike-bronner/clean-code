<?php

declare(strict_types=1);

const NO_CUSTOM_ACTIONS = 'CleanCode.Controllers.NoCustomActions';

const NO_CUSTOM_ACTIONS_WARNING = NO_CUSTOM_ACTIONS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_CUSTOM_ACTIONS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every custom public action in a controller', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 10, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 15, 'column' => 19, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 19, 'column' => 5, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 36, 'column' => 21, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

it('names the offending method in the warning message', function (): void {
    $warnings = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php')->getWarnings();

    expect($warnings[10][12][0]['message'])->toContain('export()');
});

it('ignores declarations nested inside an action', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'nested-declarations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 31, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

it('exposes a configurable allowlist', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'configured.php');

    expect(warningTuples($file))->toBe([
        ['line' => 10, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
    ]);

    $configured = analyzeFixture(
        NO_CUSTOM_ACTIONS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->allowedMethods = ['MIDDLEWARE'];
        }
    );

    expect($configured->getErrors())->toBe([])
        ->and($configured->getWarnings())->toBe([]);
});

it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('steps over a method declaration with no name', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 5, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

it('reports nothing on an unterminated class body', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
