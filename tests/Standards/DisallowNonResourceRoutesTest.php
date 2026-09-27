<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const NON_RESOURCE_ROUTES = 'CleanCode.Routes.DisallowNonResourceRoutes';

const NON_RESOURCE_ROUTES_WARNING = NON_RESOURCE_ROUTES . '.Found';

const NON_RESOURCE_ROUTES_FIXTURES = 'DisallowNonResourceRoutesSniff';

$routePath = static fn (string $fixture): string => stageFixtureOutsideTests(
    fixturePath(NON_RESOURCE_ROUTES_FIXTURES, $fixture),
    'routes'
);

$routeRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
    [NON_RESOURCE_ROUTES],
    $routePath($fixture)
);

$expectedWarnings = static fn (): array => array_map(
    static fn (array $position): array => [
        'line' => $position[0],
        'column' => $position[1],
        'source' => NON_RESOURCE_ROUTES_WARNING,
    ],
    [
        [3, 8],
        [4, 8],
        [5, 8],
        [6, 8],
        [7, 8],
        [8, 8],
        [9, 8],
        [10, 8],
        [12, 8],
        [13, 8],
        [15, 9],
        [16, 35],
        [18, 8],
        [21, 12],
    ]
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NON_RESOURCE_ROUTES);
});

it('produces no violations on the compliant fixture', function () use ($routeRun): void {
    $file = $routeRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function () use ($routeRun, $expectedWarnings): void {
    $file = $routeRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe($expectedWarnings());
});

it('names the offending verb in the warning message', function () use ($routeRun): void {
    $warnings = $routeRun('failing.php')->getWarnings();

    expect($warnings[3][8][0]['message'])->toContain('Route::get()')
        ->and($warnings[12][8][0]['message'])->toContain('Route::GET()');
});

it('inspects nothing outside a route file', function () use ($routeRun): void {
    $inRepo = analyzeWithSniffs(
        [NON_RESOURCE_ROUTES],
        fixturePath(NON_RESOURCE_ROUTES_FIXTURES, 'failing.php')
    );

    $outsideRoutes = analyzeWithSniffs(
        [NON_RESOURCE_ROUTES],
        stageFixtureOutsideTests(fixturePath(NON_RESOURCE_ROUTES_FIXTURES, 'failing.php'))
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($inRepo->getErrors())->toBe([])
        ->and($outsideRoutes->getWarnings())->toBe([])
        ->and($outsideRoutes->getErrors())->toBe([])
        ->and($routeRun('failing.php')->getWarnings())->toHaveCount(14);
});

it('exposes a configurable route-file pattern list', function () use ($expectedWarnings): void {
    $staged = stageFixtureOutsideTests(
        fixturePath(NON_RESOURCE_ROUTES_FIXTURES, 'failing.php'),
        'http'
    );

    $default = analyzeWithSniffs([NON_RESOURCE_ROUTES], $staged);

    $configured = analyzeWithSniffs(
        [NON_RESOURCE_ROUTES],
        $staged,
        static function (object $sniff) use ($staged): void {
            $sniff->routeFilePatterns = ['*/' . basename(dirname($staged)) . '/*'];
        }
    );

    expect($default->getWarnings())->toBe([])
        ->and(warningTuples($configured))->toBe($expectedWarnings());
});

it('reports detection-only warnings', function () use ($routeRun): void {
    $file = $routeRun('failing.php');

    expect($file->getWarningCount())->toBe(14)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the violation end to end through the installed package', function () use ($routePath): void {
    $staged = installedSniffRun(NON_RESOURCE_ROUTES, $routePath('failing.php'));
    $inRepo = installedSniffRun(
        NON_RESOURCE_ROUTES,
        fixturePath(NON_RESOURCE_ROUTES_FIXTURES, 'failing.php')
    );
    $passing = installedSniffRun(NON_RESOURCE_ROUTES, $routePath('passing.php'));

    expect(array_column($staged['messages'], 'source'))->toHaveCount(14)
        ->each->toBe(NON_RESOURCE_ROUTES_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
