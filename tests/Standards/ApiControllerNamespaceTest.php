<?php

declare(strict_types=1);

const API_CONTROLLER_NAMESPACE = 'CleanCode.Routes.ApiControllerNamespace';

const API_CONTROLLER_NAMESPACE_MISSING = API_CONTROLLER_NAMESPACE . '.MissingApiNamespace';

const API_CONTROLLER_NAMESPACE_UNEXPECTED = API_CONTROLLER_NAMESPACE . '.UnexpectedApiNamespace';

const API_PATH_FIXTURES = 'app/Http/Controllers/API/';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(API_CONTROLLER_NAMESPACE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every misplaced API namespace at its own line', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 21, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 29, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 39, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
    ]);
});

it('leaves a controller whose namespace matches its API path alone', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, API_PATH_FIXTURES . 'api-path-compliant.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('leaves a controller sitting directly on the controller root alone', function (): void {
    $file = analyzeFixture(
        API_CONTROLLER_NAMESPACE,
        'app/Http/Controllers/root-compliant.php'
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('does not anchor the controller root on an ancestor directory', function (string $fixture): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'two controller roots in one path' =>
        'Controllers/api/app/Http/Controllers/root-ancestor-compliant.php',
    'a non-controller namespace below an ancestor root' =>
        'Controllers/my-app/app/Http/Resources/Api/UserResource.php',
]);

it('flags a controller under an API path whose namespace has no API segment', function (): void {
    $file = analyzeFixture(
        API_CONTROLLER_NAMESPACE,
        API_PATH_FIXTURES . 'api-path-missing-namespace.php'
    );

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_MISSING],
        ['line' => 18, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_MISSING],
    ]);
});

it('marks no violation fixable', function (string $fixture, int $expected): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, $fixture);

    expect($file->getErrorCount())->toBe($expected)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    ['failing.php', 4],
    [API_PATH_FIXTURES . 'api-path-missing-namespace.php', 2],
]);

it('says nothing when the file has no path to compare against', function (): void {
    $source = <<<'PHP'
        <?php

        namespace App\Http\Controllers\API;

        class ReportController
        {
        }
        PHP;

    $onDisk = analyzeWithSniffs(
        [API_CONTROLLER_NAMESPACE],
        stageSourceOutsideTests($source, 'ReportController.php')
    );

    expect(violationTuples($onDisk))->toBe([
        ['line' => 5, 'column' => 1, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
    ]);

    $piped = analyzeStdinSource([API_CONTROLLER_NAMESPACE], $source);

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});
