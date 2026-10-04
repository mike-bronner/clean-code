<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const BOOLEAN_GET_METHOD_NAME = 'CleanCode.Naming.BooleanGetMethodName';

const BOOLEAN_GET_METHOD_NAME_ERROR = BOOLEAN_GET_METHOD_NAME . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_GET_METHOD_NAME);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every boolean getter in the failing fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 20, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 28, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 36, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 44, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 52, 'column' => 28, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 60, 'column' => 33, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 68, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 76, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 89, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 104, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 115, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

it('names the method and both replacements in the message', function (): void {
    $errors = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors();

    expect($errors[12][21][0]['message'])
        ->toContain('getVisible()')
        ->toContain('is...()')
        ->toContain('has...()');
});

it('reports the shapes PHPMD misses, and no more', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 21, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 29, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 40, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 51, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 67, 'column' => 29, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

it('reports parameterized methods by default', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'configured.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 25, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 36, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 46, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

it('reports only parameterless methods when checkParameterizedMethods is on', function (): void {
    $file = analyzeFixture(
            BOOLEAN_GET_METHOD_NAME,
            'configured.php',
            static function (object $sniff): void {
                $sniff->checkParameterizedMethods = true;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(12)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('reads an unsplittable annotation as one piece', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_split',
                static fn (): array => violationSourcesByLine(
                        analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors()
                    ),
                static fn (string $pattern): bool => $pattern === '/\s+/'
            );
    });

    expect(array_keys($expected))->toContain(28)
        ->and(array_keys($degraded))->not->toContain(28)
        ->and($diagnostics)->toBe([]);
});

it('reads a type that cannot be normalised as written', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_replace',
                static fn (): array => violationSourcesByLine(
                        analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors()
                    ),
                static fn (string $pattern): bool => $pattern === '/\s+/'
            );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
