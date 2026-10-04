<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const COUPLING_BETWEEN_OBJECTS = 'CleanCode.Metrics.CouplingBetweenObjects';

const COUPLING_BETWEEN_OBJECTS_ERROR = 'CleanCode.Metrics.CouplingBetweenObjects.Found';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COUPLING_BETWEEN_OBJECTS);
});

it('flags a class whose dependency count reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')))->toBe([
        ['line' => 16, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 51, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('names the class, its coupling value, and the threshold', function (): void {
    $errors = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors();

    expect($errors[16][1][0]['message'])->toBe(
            'The class AtTheThreshold has a coupling between objects value of 13.'
                . ' Consider to reduce the number of dependencies under 13.'
        );
    expect($errors[51][1][0]['message'])->toBe(
            'The class FarPastTheThreshold has a coupling between objects value of 18.'
                . ' Consider to reduce the number of dependencies under 13.'
        );
});

it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'boundaries.php')))->toBe([
        ['line' => 31, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 51, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('counts one dependency per source, and nothing for a near miss', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'sources.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect(array_keys(violationTuples($file)))->toHaveCount(13);
    expect(array_column(violationTuples($file), 'line'))
        ->toBe([16, 23, 28, 36, 44, 52, 60, 68, 79, 89, 96, 106, 119]);
});

it('resolves each qualified name the way it always has', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'qualified-names.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect($file->getErrors()[13][1][0]['message'])
        ->toStartWith('The class Statement has a coupling between objects value of 3.');
});

it('counts a type once however many times it is named', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'sources.php', static function (object $sniff): void {
        $sniff->maximum = 2;
    });

    expect(violationTuples($file))->toBe([]);
});

it('counts only the types the class itself names', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'passing.php', static function (object $sniff): void {
        $sniff->maximum = 12;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 140, 'column' => 22, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('counts every imported class, and neither a function nor a constant import', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'imports.php', static function (object $sniff): void {
        $sniff->maximum = 7;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 31, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 40, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 57, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('resolves an alias and its import to a single dependency', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'imports.php', static function (object $sniff): void {
        $sniff->maximum = 8;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 57, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('scores an anonymous class on its own, and ignores what names no type', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'divergences.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 22, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('names an anonymous class by its kind', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'divergences.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect($file->getErrors()[24][22][0]['message'])->toBe(
            'The anonymous class has a coupling between objects value of 3.'
                . ' Consider to reduce the number of dependencies under 1.'
        );
});

it('reports at a lowered maximum', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'configured.php', static function (object $sniff): void {
        $sniff->maximum = 3;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('stays silent above the widest coupling in the file', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'configured.php', static function (object $sniff): void {
        $sniff->maximum = 4;
    });

    expect(violationTuples($file))->toBe([]);
});

it('accepts a maximum supplied as a string, the way a ruleset supplies it', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            COUPLING_BETWEEN_OBJECTS,
            'configured.php',
            ['maximum' => '3']
        );

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

it('stays silent on an unterminated declaration without raising a PHP error', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'unclosed-class.php', static function (object $sniff): void {
            $sniff->maximum = 1;
        });
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([]);
    expect(violationTuples($file))->toBe([]);
});

it('offers no fixer', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false]);
    expect($file->getFixableCount())->toBe(0);
});

it('reads a hooked property without charging the hook body', function (): void {
    $reported = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'property-hooks.php', static function (
        object $sniff
    ): void {
        $sniff->maximum = 4;
    });
    $silent = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'property-hooks.php', static function (
        object $sniff
    ): void {
        $sniff->maximum = 5;
    });

    expect(violationTuples($reported))->toBe([
        ['line' => 13, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
    expect(violationTuples($silent))->toBe([]);
});

it('counts an unsplittable union type as a single member', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_split',
                static fn (): array => violationSourcesByLine(
                        analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors()
                    ),
                static fn (string $pattern): bool => $pattern === '/[|&]/'
            );
    });

    expect(array_keys($expected))->toContain(16)
        ->and(array_keys($degraded))->not->toContain(16)
        ->and($diagnostics)->toBe([]);
});
