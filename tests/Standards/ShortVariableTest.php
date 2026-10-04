<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;
use PHP_CodeSniffer\Ruleset;

const SHORT_VARIABLE = 'CleanCode.Naming.ShortVariable';

const SHORT_VARIABLE_TOO_SHORT = SHORT_VARIABLE . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_VARIABLE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every short name at the variable token', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 28, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 24, 'column' => 5, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 31, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 36, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 43, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 51, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 53, 'column' => 19, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 55, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 57, 'column' => 45, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 61, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 66, 'column' => 39, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 66, 'column' => 44, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 68, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 78, 'column' => 10, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 78, 'column' => 15, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 82, 'column' => 36, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 82, 'column' => 41, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 89, 'column' => 29, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 101, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 102, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 108, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 109, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 119, 'column' => 27, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 120, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 124, 'column' => 26, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('reports without offering a fix', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    expect($file->getErrorCount())->toBe(25)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('names the variable and the threshold in the message', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    $messages = $file->getErrors()[68][9];

    expect($messages[0]['message'])
        ->toBe('Avoid variables with short names like $rr. Configured minimum length is 3.');
});

it('passes a name exactly at the minimum and fails one character shorter', function (): void {
    $atMinimum = analyzeFixture(
            SHORT_VARIABLE,
            'passing.php',
            static function (object $sniff): void {
                $sniff->minimum = 3;
            }
        );

    $oneAbove = analyzeFixture(
            SHORT_VARIABLE,
            'passing.php',
            static function (object $sniff): void {
                $sniff->minimum = 4;
            }
        );

    expect($atMinimum->getErrors())->toBe([])
        ->and(array_keys($oneAbove->getErrors()))->toContain(25);
});

it('measures the name in bytes, as PHPMD does', function (): void {
    $atDefault = analyzeFixture(SHORT_VARIABLE, 'passing.php');

    $raised = analyzeFixture(
            SHORT_VARIABLE,
            'passing.php',
            static function (object $sniff): void {
                $sniff->minimum = 4;
            }
        );

    expect(array_keys($atDefault->getErrors()))->not->toContain(29)
        ->and(array_keys($raised->getErrors()))->toContain(29);
});

it('never reports a name in the exceptions list', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'failing.php',
            static function (object $sniff): void {
                $sniff->exceptions = 'ip';
            }
        );

    expect(array_keys($file->getErrors()))
        ->not->toContain(36)
        ->not->toContain(61)
        ->toContain(66);
});

it('ignores an exceptions entry written with its sigil', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'failing.php',
            static function (object $sniff): void {
                $sniff->exceptions = '$ip';
            }
        );

    expect(array_keys($file->getErrors()))->toContain(36, 61);
});

it('does not trim the exceptions list, matching PHPMD', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'failing.php',
            static function (object $sniff): void {
                $sniff->exceptions = 'ip, tf';
            }
        );

    expect(array_keys($file->getErrors()))
        ->toContain(31)
        ->not->toContain(36);
});

it('matches exceptions case-sensitively, matching PHPMD', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'failing.php',
            static function (object $sniff): void {
                $sniff->exceptions = 'IP';
            }
        );

    expect(array_keys($file->getErrors()))->toContain(36, 61);
});

it('receives null from PHPCS for an empty ruleset property', function (): void {
    [, $ruleset] = buildRuleset([SHORT_VARIABLE], true);
    $sniffClass = $ruleset->sniffCodes[SHORT_VARIABLE];

    $ruleset->setSniffProperty($sniffClass, 'minimum', ['scope' => 'sniff', 'value' => '']);

    expect($ruleset->sniffs[$sniffClass]->minimum)->toBeNull();
})->skip(
        method_exists(Ruleset::class, 'setSniffProperty') === false,
        'This PHPCS release does not expose setSniffProperty().'
    );

it('falls back to the default minimum when the configured one is unusable', function (mixed $configured): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'failing.php',
            static function (object $sniff) use ($configured): void {
                $sniff->minimum = $configured;
            }
        );

    expect($file->getErrorCount())->toBe(25);
})->with([
    'null (PHPCS empty property)' => [null],
    'empty string' => [''],
    'non-numeric' => ['abc'],
    'zero' => ['0'],
    'negative' => ['-1'],
    'float-ish' => ['2.5'],
]);

it('honours a numeric string threshold from a ruleset', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'passing.php',
            static function (object $sniff): void {
                $sniff->minimum = '4';
            }
        );

    expect(array_keys($file->getErrors()))->toContain(25, 29);
});

it('exempts a short name only where PHPMD allows the context', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'contexts.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 37, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 50, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('does not count a static property access as an occurrence', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'static-access.php');

    expect(violationTuples($file))->toBe([
        ['line' => 27, 'column' => 19, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 32, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('never reports the implicit receiver, however it is written', function (): void {
    $file = analyzeFixture(
            SHORT_VARIABLE,
            'implicit-receiver.php',
            static function (object $sniff): void {
                $sniff->minimum = 5;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 40, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 47, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 54, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 59, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 65, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('reports a trait member once, where PHPMD reports it twice', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'trait-method.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 25, 'column' => 29, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('gives a file one procedural scope across reopened tags', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'reopened-tags.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 22, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('reports an interpolated name on its own line', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'multiline-string.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('refuses to read a declaration it cannot bound', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'malformed-declaration.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 24, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('reports shapes that PHPMD cannot see or exempts wholesale', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 35, 'column' => 30, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 36, 'column' => 25, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 38, 'column' => 44, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 50, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 65, 'column' => 12, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 67, 'column' => 26, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 69, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

it('reports through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('ShortVariableSniff', 'failing.php'));

    $lines = array_keys(array_filter(
            allViolationSourcesByLine($file),
            static fn (array $sources): bool => in_array(SHORT_VARIABLE_TOO_SHORT, $sources, true)
        ));

    expect($lines)->toBe([
        22, 24, 31, 36, 43, 51, 53, 55, 57, 61, 66,
        68, 78, 82, 89, 101, 102, 108, 109, 119, 120, 124,
    ]);
});

it('reports no interpolated name when the string cannot be read', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(SHORT_VARIABLE, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_match_all',
                static fn (): array => violationSourcesByLine(
                        analyzeFixture(SHORT_VARIABLE, 'failing.php')->getErrors()
                    ),
                static fn (string $pattern): bool => str_contains($pattern, '\$\{?([a-zA-Z_')
            );
    });

    expect(array_keys($expected))->toContain(101)
        ->and(array_keys($degraded))->not->toContain(101)
        ->and($diagnostics)->toBe([]);
});
