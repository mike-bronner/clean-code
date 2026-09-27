<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const BOOLEAN_ARGUMENT_FLAG = 'CleanCode.Functions.DisallowBooleanArgumentFlag';

const BOOLEAN_ARGUMENT_FLAG_ERROR = BOOLEAN_ARGUMENT_FLAG . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_ARGUMENT_FLAG);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every boolean flag argument in the failing fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 46, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 11, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 15, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 19, 'column' => 34, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 23, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 27, 'column' => 37, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 31, 'column' => 39, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 35, 'column' => 53, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 39, 'column' => 35, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 39, 'column' => 46, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 45, 'column' => 36, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 47, 'column' => 27, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 55, 'column' => 34, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 60, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 63, 'column' => 20, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

it('names the declaration and the parameter in the message', function (): void {
    $errors = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors();

    expect($errors[11][28][0]['message'])
        ->toContain('method render()')
        ->toContain('$withHeader');
});

it('reports the two shapes PHPMD misses', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 31, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 13, 'column' => 24, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

it('exempts nothing by default', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'configured.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 38, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 9, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 16, 'column' => 45, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 21, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

it('exempts the classes named by the exceptions property', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ExemptedRenderer';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

it('matches the exceptions list case-sensitively', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'exemptedrenderer';
        }
    );

    expect(violationTuples($file))->toHaveCount(6);
});

it('exempts the names matched by the ignore pattern', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignorepattern = '/^(__construct|get.*Filtered)$/';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 21, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

it('reports everything when the ignore pattern is malformed', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    }, E_WARNING);

    try {
        $file = analyzeFixture(
            BOOLEAN_ARGUMENT_FLAG,
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignorepattern = 'not-a-pattern';
            }
        );
    } finally {
        restore_error_handler();
    }

    expect($raised)->not->toBeEmpty()
        ->and($raised[0])->toContain('Delimiter must not be alphanumeric')
        ->and(violationTuples($file))->toHaveCount(6);
});

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reads a type hint that cannot be normalised as written', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
