<?php

declare(strict_types=1);

pest()->group('arch');

const PERSISTENCE = 'CleanCode.Models.DisallowExternalPersistenceCalls';

const PERSISTENCE_WARNING = PERSISTENCE . '.Found';

$stagedRun = static fn (string $fixture, ?callable $configure = null) => analyzeWithSniffs(
        [PERSISTENCE],
        stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', $fixture)),
        $configure
    );

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PERSISTENCE);
});

it('produces no violations on the compliant fixture', function () use ($stagedRun): void {
    $file = $stagedRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
            5 => [PERSISTENCE_WARNING],
            6 => [PERSISTENCE_WARNING],
            7 => [PERSISTENCE_WARNING],
            8 => [PERSISTENCE_WARNING],
            9 => [PERSISTENCE_WARNING],
            10 => [PERSISTENCE_WARNING],
            11 => [PERSISTENCE_WARNING],
            12 => [PERSISTENCE_WARNING],
            18 => [PERSISTENCE_WARNING],
            23 => [PERSISTENCE_WARNING],
            27 => [PERSISTENCE_WARNING],
            28 => [PERSISTENCE_WARNING],
            29 => [PERSISTENCE_WARNING],
            30 => [PERSISTENCE_WARNING],
            31 => [PERSISTENCE_WARNING],
            32 => [PERSISTENCE_WARNING],
            33 => [PERSISTENCE_WARNING],
            34 => [PERSISTENCE_WARNING],
            35 => [PERSISTENCE_WARNING],
            36 => [PERSISTENCE_WARNING],
            37 => [PERSISTENCE_WARNING],
            38 => [PERSISTENCE_WARNING],
            39 => [PERSISTENCE_WARNING],
        ]);
});

it('names the offending method in the warning message', function () use ($stagedRun): void {
    $warnings = $stagedRun('failing.php')->getWarnings();

    expect($warnings[3][8][0]['message'])->toContain('save()')
        ->and($warnings[8][8][0]['message'])->toContain('SAVE()');
});

it('exposes a configurable persistence-method list', function () use ($stagedRun): void {
    expect(array_keys($stagedRun('configured.php')->getWarnings()))->toBe([4]);

    $configured = $stagedRun(
            'configured.php',
            static function (object $sniff): void {
                $sniff->persistenceMethods = ['persist'];
            }
        );

    expect(array_keys($configured->getWarnings()))->toBe([3]);
});

it('checks a static-call write only on a class in a model namespace', function () use ($stagedRun): void {
    $file = $stagedRun('static-calls.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            13 => [PERSISTENCE_WARNING],
            14 => [PERSISTENCE_WARNING],
            15 => [PERSISTENCE_WARNING],
            16 => [PERSISTENCE_WARNING],
            25 => [PERSISTENCE_WARNING],
            26 => [PERSISTENCE_WARNING],
            30 => [PERSISTENCE_WARNING],
            38 => [PERSISTENCE_WARNING],
            51 => [PERSISTENCE_WARNING],
        ]);
});

it('resolves relative, aliased and partly imported class names', function () use ($stagedRun): void {
    $file = $stagedRun('resolution.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            10 => [PERSISTENCE_WARNING],
            11 => [PERSISTENCE_WARNING],
            12 => [PERSISTENCE_WARNING],
            13 => [PERSISTENCE_WARNING],
        ]);
});

it('resolves a name only through the imports of its own unbraced namespace block', function () use ($stagedRun): void {
    expect(violationSourcesByLine($stagedRun('namespace-blocks.php')->getWarnings()))->toBe([
        9 => [PERSISTENCE_WARNING],
        18 => [PERSISTENCE_WARNING],
    ]);
});

it('resolves a name only through the imports of its own braced namespace block', function () use ($stagedRun): void {
    expect(violationSourcesByLine($stagedRun('braced-blocks.php')->getWarnings()))->toBe([
        7 => [PERSISTENCE_WARNING],
        15 => [PERSISTENCE_WARNING],
        21 => [PERSISTENCE_WARNING],
    ]);
});

it('reads the model namespaces from a configurable list', function () use ($stagedRun): void {
    $file = $stagedRun(
            'static-calls.php',
            static function (object $sniff): void {
                $sniff->modelNamespaces = ['\\Domain\\Accounts\\', 'Stripe'];
            }
        );

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        20 => [PERSISTENCE_WARNING],
        25 => [PERSISTENCE_WARNING],
        30 => [PERSISTENCE_WARNING],
        49 => [PERSISTENCE_WARNING],
        50 => [PERSISTENCE_WARNING],
    ]);
});

it('passes a write on a receiver whose stated class is not a model', function () use ($stagedRun): void {
    $file = $stagedRun('stated-receivers.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            21 => [PERSISTENCE_WARNING],
            22 => [PERSISTENCE_WARNING],
            45 => [PERSISTENCE_WARNING],
        ]);
});

it('still flags a write on a model, the DB facade or a receiver of unknown type', function () use ($stagedRun): void {
    $file = $stagedRun('model-receivers.php');
    $warned = [
        9, 10, 11, 12, 13, 14, 15, 16, 17, 20, 22, 28, 33, 39, 44, 49, 54, 59, 64, 70,
        76, 81, 86, 91, 96, 101, 106, 122, 127, 134, 138,
    ];

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))
        ->toBe(array_fill_keys($warned, [PERSISTENCE_WARNING]));
});

it('ships App\\Models as the default model namespace', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = new $ruleset->sniffCodes[PERSISTENCE]();

    expect($sniff->modelNamespaces)->toBe(['App\\Models']);
});

it('is scoped out of test paths', function () use ($stagedRun): void {
    $inRepo = analyzeWithSniffs(
            [PERSISTENCE],
            fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php')
        );

    expect($inRepo->getWarnings())->toBe([])
        ->and($stagedRun('failing.php')->getWarnings())->toHaveCount(25);
});

it('is scoped out of the database seeders, migrations and factories folders', function (string $folder): void {
    $failing = fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php');

    expect(analyzeWithSniffs([PERSISTENCE], stageFixtureOutsideTests($failing, $folder))->getWarnings())->toBe([]);
})->with([
    'database/seeders',
    'database/migrations',
    'database/factories',
]);

it('still checks the database folder and its other subfolders', function (string $folder): void {
    $failing = fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php');

    expect(analyzeWithSniffs([PERSISTENCE], stageFixtureOutsideTests($failing, $folder))->getWarnings())
        ->toHaveCount(25);
})->with([
    'database',
    'database/seeds',
    'app/Models',
    'seeders',
]);

it('handles a truncated chain without falling over', function () use ($stagedRun): void {
    $file = $stagedRun(
            'unterminated.php',
            static function (object $sniff): void {
                $sniff->persistenceMethods = ['save', 'list'];
            }
        );

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
            5 => [PERSISTENCE_WARNING],
        ]);
});

it('reports detection-only warnings', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getWarningCount())->toBe(25)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php');

    $staged = installedSniffRun(PERSISTENCE, stageFixtureOutsideTests($failing));
    $inRepo = installedSniffRun(PERSISTENCE, $failing);
    $seeder = installedSniffRun(PERSISTENCE, stageFixtureOutsideTests($failing, 'database/seeders'));
    $passing = installedSniffRun(
            PERSISTENCE,
            stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', 'passing.php'))
        );

    expect(array_column($staged['messages'], 'source'))->toHaveCount(25)
        ->each->toBe(PERSISTENCE_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(2)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($seeder['messages'])->toBe([])
        ->and($seeder['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
