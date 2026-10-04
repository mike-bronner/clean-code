<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

pest()->group('arch');

const NO_HTTP_FAKES = 'CleanCode.Testing.NoHttpFakesInIntegrationTests';

const NO_HTTP_FAKES_FAKE = NO_HTTP_FAKES . '.FakedHttpClient';

const NO_HTTP_FAKES_MOCK = NO_HTTP_FAKES . '.MockedHttpClient';

const NO_HTTP_FAKES_FIXTURES = 'NoHttpFakesInIntegrationTestsSniff';

$integrationPath = static fn (string $fixture): string => stageFixtureOutsideTests(
        fixturePath(NO_HTTP_FAKES_FIXTURES, $fixture),
        'tests/Integration'
    );

$integrationRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $integrationPath($fixture)
    );

$expectedWarnings = static fn (): array => array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => $position[2],
        ],
        [
            [3, 7, NO_HTTP_FAKES_FAKE],
            [4, 7, NO_HTTP_FAKES_FAKE],
            [5, 7, NO_HTTP_FAKES_FAKE],
            [6, 8, NO_HTTP_FAKES_FAKE],
            [7, 34, NO_HTTP_FAKES_FAKE],
            [8, 7, NO_HTTP_FAKES_FAKE],
            [9, 7, NO_HTTP_FAKES_FAKE],
            [10, 7, NO_HTTP_FAKES_FAKE],
            [12, 8, NO_HTTP_FAKES_MOCK],
            [13, 8, NO_HTTP_FAKES_MOCK],
            [14, 9, NO_HTTP_FAKES_MOCK],
            [15, 10, NO_HTTP_FAKES_MOCK],
            [16, 10, NO_HTTP_FAKES_MOCK],
            [17, 8, NO_HTTP_FAKES_MOCK],
            [18, 8, NO_HTTP_FAKES_MOCK],
        ]
    );

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_HTTP_FAKES);
});

it('produces no violations on the compliant fixture', function () use ($integrationRun): void {
    $file = $integrationRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function () use ($integrationRun, $expectedWarnings): void {
    $file = $integrationRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe($expectedWarnings());
});

it('names the doubled dependency in the warning message', function () use ($integrationRun): void {
    $warnings = $integrationRun('failing.php')->getWarnings();

    expect($warnings[3][7][0]['message'])->toContain('Http::fake()')
        ->and($warnings[9][7][0]['message'])->toContain('Http::FAKE()')
        ->and($warnings[18][8][0]['message'])->toContain('\Illuminate\Http\Client\PendingRequest');
});

it('inspects nothing outside an integration test', function () use ($integrationRun): void {
    $inRepo = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php')
    );

    $outsideIntegration = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        stageFixtureOutsideTests(fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php'), 'tests/Feature')
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($inRepo->getErrors())->toBe([])
        ->and($outsideIntegration->getWarnings())->toBe([])
        ->and($outsideIntegration->getErrors())->toBe([])
        ->and($integrationRun('failing.php')->getWarnings())->toHaveCount(15);
});

it('exposes a configurable integration-test pattern list', function () use ($expectedWarnings): void {
    $staged = stageFixtureOutsideTests(
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php'),
        'suites/e2e'
    );

    $default = analyzeWithSniffs([NO_HTTP_FAKES], $staged);

    $configured = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff) use ($staged): void {
            $sniff->integrationPatterns = ['*/' . basename(dirname($staged)) . '/*'];
        }
    );

    expect($default->getWarnings())->toBe([])
        ->and(warningTuples($configured))->toBe($expectedWarnings());
});

it('exposes the detection lists as configurable properties', function () use ($integrationPath): void {
    $staged = $integrationPath('failing.php');

    $withoutFakes = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->fakeMethods = ['fakeSequence'];
        }
    );

    $withoutCreators = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->mockCreators = ['createMock'];
        }
    );

    $withoutClients = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->httpClientClasses = [];
        }
    );

    expect(array_column(warningTuples($withoutFakes), 'source'))
        ->toBe(array_merge([NO_HTTP_FAKES_FAKE], array_fill(0, 7, NO_HTTP_FAKES_MOCK)))
        ->and(array_column(warningTuples($withoutCreators), 'line'))
        ->toBe([3, 4, 5, 6, 7, 8, 9, 10, 12, 18])
        ->and(array_column(warningTuples($withoutClients), 'source'))
        ->toBe(array_fill(0, 8, NO_HTTP_FAKES_FAKE));
});

it('reports detection-only warnings', function () use ($integrationRun): void {
    $file = $integrationRun('failing.php');

    expect($file->getWarningCount())->toBe(15)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the violation end to end through the installed package', function () use ($integrationPath): void {
    $staged = installedSniffRun(NO_HTTP_FAKES, $integrationPath('failing.php'));
    $inRepo = installedSniffRun(
        NO_HTTP_FAKES,
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php')
    );
    $passing = installedSniffRun(NO_HTTP_FAKES, $integrationPath('passing.php'));

    expect(array_values(array_unique(array_column($staged['messages'], 'source'))))
        ->toBe([NO_HTTP_FAKES_FAKE, NO_HTTP_FAKES_MOCK])
        ->and($staged['messages'])->toHaveCount(15)
        ->and(array_values(array_unique(array_column($staged['messages'], 'type'))))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(2)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
