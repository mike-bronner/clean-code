<?php

declare(strict_types=1);

const LAZY_LOADING = 'CleanCode.Models.RequireLazyLoadingPrevention';

const LAZY_LOADING_WARNING = LAZY_LOADING . '.Missing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LAZY_LOADING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('accepts strict mode as enabling the check', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'strict-mode.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('accepts the call anywhere in the class body', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'delegated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a provider without the safety check on its class declaration', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 3, 'column' => 1, 'source' => LAZY_LOADING_WARNING],
        ]);
});

it('names the provider class in the warning message', function (): void {
    $warnings = analyzeFixture(LAZY_LOADING, 'failing.php')->getWarnings();

    expect($warnings[3][1][0]['message'])->toContain('AppServiceProvider');
});

it('rejects shapes that only mention the method', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'near-miss.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => LAZY_LOADING_WARNING],
    ]);
});

it('scopes the search to the watched class body', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'scoped.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => LAZY_LOADING_WARNING],
    ]);
});

it('exposes a configurable watched-provider list', function (): void {
    expect(analyzeFixture(LAZY_LOADING, 'configured.php')->getWarnings())->toBe([]);

    $configured = analyzeFixture(
        LAZY_LOADING,
        'configured.php',
        static function (object $sniff): void {
            $sniff->serviceProviderClasses = ['modelserviceprovider'];
        }
    );

    expect(warningTuples($configured))->toBe([
        ['line' => 3, 'column' => 1, 'source' => LAZY_LOADING_WARNING],
    ]);
});

it('handles a truncated call without falling over', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'truncated.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => LAZY_LOADING_WARNING],
    ]);
});

it('skips comments between the double colon and the method name', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'spaced.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('skips comments between the method name and its argument list', function (): void {
    $file = analyzeFixture(
        LAZY_LOADING,
        'spaced.php',
        static function (object $sniff): void {
            $sniff->serviceProviderClasses = ['ModelServiceProvider'];
        }
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'failing.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
