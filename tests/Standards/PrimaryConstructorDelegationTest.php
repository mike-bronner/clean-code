<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const DELEGATION = 'CleanCode.Constructors.PrimaryConstructorDelegation';

const DELEGATION_WARNING = DELEGATION . '.Missing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DELEGATION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DELEGATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every named constructor that bypasses the primary constructor', function (): void {
    $file = analyzeFixture(DELEGATION, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            36 => [DELEGATION_WARNING],
            41 => [DELEGATION_WARNING],
            49 => [DELEGATION_WARNING],
            54 => [DELEGATION_WARNING],
            59 => [DELEGATION_WARNING],
            64 => [DELEGATION_WARNING],
            81 => [DELEGATION_WARNING],
        ]);
});

it('reports on the function keyword', function (): void {
    $tuples = warningTuples(analyzeFixture(DELEGATION, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 36, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 41, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 49, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 54, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 59, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 64, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 81, 'column' => 19, 'source' => DELEGATION_WARNING],
    ]);
});

it('names the method in the warning message', function (): void {
    $warnings = analyzeFixture(DELEGATION, 'failing.php')->getWarnings();

    expect($warnings[36][19][0]['message'])->toContain('fromSerialized()');
});

it('rejects shapes that only resemble delegation', function (): void {
    $file = analyzeFixture(DELEGATION, 'near-miss.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            46 => [DELEGATION_WARNING],
            51 => [DELEGATION_WARNING],
            56 => [DELEGATION_WARNING],
            61 => [DELEGATION_WARNING],
            66 => [DELEGATION_WARNING],
            71 => [DELEGATION_WARNING],
            76 => [DELEGATION_WARNING],
            81 => [DELEGATION_WARNING],
            86 => [DELEGATION_WARNING],
            91 => [DELEGATION_WARNING],
            96 => [DELEGATION_WARNING],
            108 => [DELEGATION_WARNING],
        ]);
});

it('inspects trait-declared named constructors, and discloses the consumer-typed blind spot', function (): void {
    $file = analyzeFixture(DELEGATION, 'trait.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            41 => [DELEGATION_WARNING],
        ]);
});

it('reads a root-qualified name as a different class inside a namespace', function (): void {
    $file = analyzeFixture(DELEGATION, 'namespaced.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            38 => [DELEGATION_WARNING],
            43 => [DELEGATION_WARNING],
        ]);
});

it('passes over a truncated file without falling over', function (): void {
    $file = analyzeFixture(DELEGATION, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(DELEGATION, 'failing.php');

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reads an unsplittable union return type as a single member', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(DELEGATION, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_split',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(DELEGATION, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/[|&]/'
        );
    });

    expect(array_keys($expected))->toContain(59)
        ->and(array_keys($degraded))->not->toContain(59)
        ->and($diagnostics)->toBe([]);
});
