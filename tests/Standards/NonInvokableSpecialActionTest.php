<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

pest()->group('arch');

const SPECIAL_ACTION = 'CleanCode.Routes.NonInvokableSpecialAction';

const SPECIAL_ACTION_FOUND = SPECIAL_ACTION . '.Found';

$routeRun = static fn (string $fixture, string $subdirectory = 'routes') => analyzeWithSniffs(
    [SPECIAL_ACTION],
    stageFixtureOutsideTests(fixturePath('NonInvokableSpecialActionSniff', $fixture), $subdirectory)
);

$routeSource = static fn (string $source) => analyzeWithSniffs(
    [SPECIAL_ACTION],
    stageProjectOutsideTests(['routes/web.php' => "<?php\n\n" . $source . "\n"])
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SPECIAL_ACTION);
});

it('produces no violations on the compliant fixture', function () use ($routeRun): void {
    $file = $routeRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every non-invokable action shape at its own line and column', function () use ($routeRun): void {
    expect(warningTuples($routeRun('failing.php')))->toBe([
        ['line' => 7, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 8, 'column' => 31, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 9, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 10, 'column' => 28, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 11, 'column' => 31, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 12, 'column' => 32, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 13, 'column' => 27, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 16, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 17, 'column' => 28, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 21, 'column' => 48, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 22, 'column' => 47, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 26, 'column' => 32, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 29, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 33, 'column' => 31, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 37, 'column' => 31, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 41, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 42, 'column' => 27, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 49, 'column' => 38, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 50, 'column' => 44, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 51, 'column' => 20, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 52, 'column' => 62, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 56, 'column' => 53, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 59, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 65, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 70, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 77, 'column' => 38, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 78, 'column' => 58, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 84, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 85, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 86, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 90, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 91, 'column' => 30, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 97, 'column' => 29, 'source' => SPECIAL_ACTION_FOUND],
        ['line' => 98, 'column' => 32, 'source' => SPECIAL_ACTION_FOUND],
    ]);
});

it('reports warnings and never errors', function () use ($routeRun): void {
    expect($routeRun('failing.php')->getErrors())->toBe([])
        ->and($routeRun('failing.php')->getWarningCount())->toBe(34);
});

it('stays silent on each near-miss shape', function (string $source) use ($routeSource): void {
    $file = $routeSource($source);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'invokable ::class action' => ["Route::get('/a', ArchiveController::class);"],
    'fully qualified invokable action' => ["Route::get('/a', \\App\\Http\\ArchiveController::class);"],
    'RESTful array action' => ["Route::get('/a', [PostController::class, 'index']);"],
    'RESTful string action' => ["Route::get('/a', 'PostController@destroy');"],
    'dynamic first element' => ["Route::get('/a', [\$controller, 'archive']);"],
    'call as first element' => ["Route::get('/a', [resolve(), 'archive']);"],
    'dynamic second element' => ["Route::get('/a', [PostController::class, \$method]);"],
    'class constant second element' => ["Route::get('/a', [PostController::class, self::ARCHIVE]);"],
    'interpolated string action' => ['Route::get(\'/a\', "PostController@{$method}");'],
    'associative uses action' => ["Route::get('/a', ['uses' => 'PostController@archive']);"],
    'associative uses array action' => ["Route::get('/a', ['uses' => [PostController::class, 'archive']]);"],
    'closure action' => ["Route::get('/a', function () {\n    return 1;\n});"],
    'arrow function action' => ["Route::get('/a', fn () => 1);"],
    'match with invokable action' => ["Route::match(['get', 'post'], '/a', ArchiveController::class);"],
    'match with RESTful action' => ["Route::match(['get'], '/a', [PostController::class, 'show']);"],
    'verb without an action argument' => ["Route::get('/a');"],
    'match without an action argument' => ["Route::match(['get', 'post'], '/a');"],
    'another facade with the same verb' => ["Http::get('/a', [ApiClient::class, 'fetch']);"],
    'a router-like receiver that is not Route' => ["Router::get('/a', [PostController::class, 'archive']);"],
    'lower cased receiver' => ["route::get('/a', [PostController::class, 'archive']);"],
    'upper cased receiver' => ["ROUTE::get('/a', [PostController::class, 'archive']);"],
    'mixed case receiver on a string action' => ["RoUtE::post('/a', 'PostController@archive');"],
    'resource registration' => ["Route::resource('posts', PostController::class);"],
    'named invokable action' => ["Route::get(uri: '/a', action: ArchiveController::class);"],
    'named RESTful action' => ["Route::get(uri: '/a', action: [PostController::class, 'index']);"],
    'named dynamic action' => ["Route::get(uri: '/a', action: [PostController::class, \$method]);"],
    'mis-cased action name' => ["Route::get(uri: '/a', Action: [PostController::class, 'archive']);"],
    'named uri without an action' => ["Route::get(uri: '/a');"],
    'action name with no value after it' => ["Route::get(uri: '/a', action:);"],
    'single element array action' => ["Route::get('/a', [PostController::class]);"],
    'three element array action' => ["Route::get('/a', [PostController::class, 'archive', 'extra']);"],
    'static::class first element' => ["Route::get('/a', [static::class, 'archive']);"],
    'self::class first element' => ["Route::get('/a', [self::class, 'archive']);"],
    'parent::class first element' => ["Route::get('/a', [parent::class, 'archive']);"],
    'string holding an @ that names no controller' => ["Route::get('/a', 'support@example.com');"],
    'string action with no method' => ["Route::get('/a', 'PostController@');"],
    'chained builder registration' => ["Route::middleware('auth')->get('/a', [PostController::class, 'archive']);"],
    'concatenated string action' => ["Route::get('/a', 'PostController@archive' . \$suffix);"],
    'concatenated array method' => ["Route::get('/a', [PostController::class, 'archive' . \$suffix]);"],
    'nowdoc action' => ["Route::get('/a', <<<'ACTION'\nPostController@archive\nACTION);"],
    'heredoc action' => ["Route::get('/a', <<<ACTION\nPostController@archive\nACTION);"],
    'escaped newline in the class name' => ['Route::get(\'/a\', "Post\nController@archive");'],
    'escaped quote in the class name' => ['Route::get(\'/a\', "Post\"Controller@archive");'],
    'codepoint escape in the method name' => ['Route::get(\'/a\', "PostController@arch\u{0069}ve");'],
]);

it('names the targeted method in the warning', function (string $source, string $method) use ($routeSource): void {
    $warnings = $routeSource($source)->getWarnings();
    $first = reset($warnings);
    $messages = reset($first);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toContain($method . '()');
})->with([
    'array action' => ["Route::get('/a', [PostController::class, 'archive']);", 'archive'],
    'long form array action' => ["Route::get('/a', array(PostController::class, 'archive'));", 'archive'],
    'string action' => ["Route::get('/a', 'PostController@archive');", 'archive'],
    'double quoted string action' => ['Route::get(\'/a\', "PostController@archive");', 'archive'],
    'double quoted array method' => ['Route::get(\'/a\', [PostController::class, "archive"]);', 'archive'],
    'namespaced string action' => ["Route::get('/a', 'App\\Http\\PostController@archive');", 'archive'],
    'match action at the third argument' => [
        "Route::match(['get', 'post'], '/a', [PostController::class, 'export']);",
        'export',
    ],
    'upper cased verb' => ["Route::GET('/a', [PostController::class, 'archive']);", 'archive'],
    'leading separator on the facade' => ["\\Route::get('/a', [PostController::class, 'archive']);", 'archive'],
    'restful name differing only in case' => ["Route::get('/a', [PostController::class, 'Show']);", 'Show'],
    'named action after a positional uri' => [
        "Route::get('/a', action: [PostController::class, 'archive']);",
        'archive',
    ],
    'fully named call' => [
        "Route::post(uri: '/a', action: [PostController::class, 'publish']);",
        'publish',
    ],
    'named action written before the uri' => [
        "Route::get(action: 'PostController@promote', uri: '/a');",
        'promote',
    ],
    'named action in a match call' => [
        "Route::match(['get'], uri: '/a', action: [PostController::class, 'rebuild']);",
        'rebuild',
    ],
    'arrow function before the action' => [
        "Route::get(fn () => '/a', [PostController::class, 'archive']);",
        'archive',
    ],
    'arrow function before a match action' => [
        "Route::match(fn () => ['get'], '/a', [PostController::class, 'export']);",
        'export',
    ],
    'double quoted escaped namespace separator' => [
        'Route::get(\'/a\', "App\\\\Http\\\\PostController@archive");',
        'archive',
    ],
    'single quoted escaped namespace separator' => [
        "Route::get('/a', 'App\\\\Http\\\\PostController@archive');",
        'archive',
    ],
    'hex escape in the method name' => ['Route::get(\'/a\', "PostController@arch\x69ve");', 'archive'],
    'octal escape in the method name' => ['Route::get(\'/a\', "PostController@arch\151ve");', 'archive'],
    'upper cased hex escape in the method name' => [
        'Route::get(\'/a\', "PostController@arch\X69ve");',
        'archive',
    ],
    'malformed hex escape in the class name' => [
        'Route::get(\'/a\', "App\xZoneController@archive");',
        'archive',
    ],
]);

it('inspects a file only under a routes directory', function (string $directory, int $expected) use ($routeRun): void {
    expect($routeRun('failing.php', $directory)->getWarningCount())->toBe($expected);
})->with([
    'routes' => ['routes', 34],
    'nested under routes' => ['routes/admin', 34],
    'app' => ['app', 0],
    'app/Providers' => ['app/Providers', 0],
    'tests' => ['tests', 0],
]);

it('honours a ruleset-configured routeFilePatterns', function (): void {
    $staged = stageFixtureOutsideTests(fixturePath('NonInvokableSpecialActionSniff', 'failing.php'), 'app');
    $configured = analyzeWithSniffs(
        [SPECIAL_ACTION],
        $staged,
        static function (object $sniff): void {
            $sniff->routeFilePatterns = ['*/app/*'];
        }
    );

    expect($configured->getWarningCount())->toBe(34);
});

it('stays silent on input with no path', function (): void {
    $file = analyzeStdinSource(
        [SPECIAL_ACTION],
        "<?php\n\nRoute::get('/a', [PostController::class, 'archive']);\n"
    );

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
});

it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('NonInvokableSpecialActionSniff', 'failing.php');

    $staged = installedSniffRun(SPECIAL_ACTION, stageFixtureOutsideTests($failing, 'routes'));
    $inRepo = installedSniffRun(SPECIAL_ACTION, $failing);
    $passing = installedSniffRun(
        SPECIAL_ACTION,
        stageFixtureOutsideTests(fixturePath('NonInvokableSpecialActionSniff', 'passing.php'), 'routes')
    );

    expect(array_column($staged['messages'], 'source'))->toHaveCount(34)
        ->each->toBe(SPECIAL_ACTION_FOUND)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(2)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});

it('survives an escaped action whose literal cannot be evaluated', function (
    string $source,
    string $pattern,
    bool $survives
) use ($routeSource): void {
    $expected = allViolationSourcesByLine($routeSource($source));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function () use ($routeSource, $source, $pattern): array {
        return PregFailure::during(
            'preg_replace_callback',
            static fn (): array => allViolationSourcesByLine($routeSource($source)),
            static fn (string $armed): bool => $armed === $pattern
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($survives ? $expected : [])
        ->and($diagnostics)->toBe([]);
})->with([
    'single-quoted' => ["Route::get('/posts/rehome', 'PostController@rehome');", '/\\\\(.)/s', true],
    'double-quoted' => [
        'Route::get("/posts/rehome", "PostController@reh\x6fme");',
        '/\\\\([xX][0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s',
        false,
    ],
    'double-quoted, nothing to unescape' => [
        'Route::get("/posts/rehome", "PostController@rehome");',
        '/\\\\([xX][0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s',
        true,
    ],
]);
