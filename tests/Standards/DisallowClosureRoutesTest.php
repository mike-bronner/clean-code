<?php

declare(strict_types=1);

const DISALLOW_CLOSURE_ROUTES = 'CleanCode.Routes.DisallowClosureRoutes';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_CLOSURE_ROUTES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every closure route action at its own position', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    $positions = array_map(
        static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
        violationTuples($file)
    );

    expect($positions)->toBe([
        [9, 18],
        [12, 19],
        [13, 25],
        [16, 27],
        [17, 21],
        [20, 22],
        [23, 18],
        [29, 37],
        [34, 17],
        [39, 46],
        [44, 38],
        [45, 56],
        [50, 26],
        [57, 22],
        [64, 18],
        [77, 57],
    ]);
});

it('flags every registration verb the standard names', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    $verbs = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $verbs[] = explode('(', explode('::', $violation['message'])[1])[0];
            }
        }
    }

    $verbs = array_values(array_unique($verbs));
    sort($verbs);

    expect($verbs)->toBe([
        'any',
        'delete',
        'fallback',
        'get',
        'match',
        'options',
        'patch',
        'post',
        'put',
    ]);
});

it('reports a route action once, not once per closure inside it', function (array $lines): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    $reported = array_filter(
        violationTuples($file),
        static fn (array $tuple): bool => in_array($tuple['line'], $lines, true)
    );

    expect($reported)->toHaveCount(1);
})->with([
    'group callback wrapping a closure action' => [[54, 55, 56, 57, 58, 59, 60]],
    'closure action declaring an inner closure' => [[63, 64, 65, 66, 67, 68]],
]);

it('reports at error severity under its own source', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    $sources = array_values(array_unique(array_column(violationTuples($file), 'source')));

    expect($file->getWarnings())->toBe([])
        ->and($sources)->toBe([DISALLOW_CLOSURE_ROUTES . '.ClosureAction']);
});

it('offers no fix for a closure action', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    expect(array_unique(violationFixableFlags($file)))->toBe([false])
        ->and(file_exists(__DIR__ . '/../fixtures/DisallowClosureRoutesSniff/autofixed.php'))->toBeFalse();
});
