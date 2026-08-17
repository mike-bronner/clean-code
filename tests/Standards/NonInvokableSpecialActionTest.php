<?php

/**
 * Tests the custom CleanCode.Routes.NonInvokableSpecialAction sniff (Routes:
 * Conventions (Do / Do Not), #65, focused slice #249). Fixtures live in
 * tests/fixtures/NonInvokableSpecialActionSniff/: every compliant and
 * near-miss action shape in passing.php, every flagged action shape in
 * failing.php. The rule is detection-only, so there is no autofixed fixture.
 *
 * The sniff gates itself on the file's path through its own
 * $routeFilePatterns property, and PHPCS decides that from the path alone — so
 * these fixtures report nothing where they live, under tests/. Every assertion
 * about the sniff's own behaviour therefore runs against a copy staged into a
 * `routes/` directory outside the repository ($routeRun below), and the gate
 * itself is pinned separately by the file-gate tests, which drive the same
 * bytes from four different paths so the silence has to come from the path
 * rather than from the sniff having nothing to say.
 *
 * That same gate is why the sniff is held out of tests/Contract/'s sweep — see
 * the note beside SWEPT_WARNING_SNIFFS in tests/Sniffs.php. The floor the
 * sweep would have applied (registered in the master ruleset, silent on
 * passing.php, warning on failing.php, and never an error) is applied here
 * instead, against staged copies the gate can actually see.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const SPECIAL_ACTION = 'CleanCode.Routes.NonInvokableSpecialAction';

const SPECIAL_ACTION_FOUND = SPECIAL_ACTION . '.Found';

// Fixtures are copied into a routes/ directory outside the repository before
// processing, because the sniff restricts itself to route paths. The staged
// copies are removed by the afterEach() hook in tests/Pest.php.
$routeRun = static fn (string $fixture, string $subdirectory = 'routes') => analyzeWithSniffs(
    [SPECIAL_ACTION],
    stageFixtureOutsideTests(fixturePath('NonInvokableSpecialActionSniff', $fixture), $subdirectory)
);

// One registration at a time, written into a real routes/web.php outside the
// repository. This is what lets a near-miss be asserted *individually*: a
// shape that stopped being skipped reports on its own file rather than being
// masked by the rest of passing.php.
$routeSource = static fn (string $source) => analyzeWithSniffs(
    [SPECIAL_ACTION],
    stageProjectOutsideTests(['routes/web.php' => "<?php\n\n" . $source . "\n"])
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SPECIAL_ACTION);
});

/**
 * The compliant shapes and every near-miss stay silent together. The
 * per-shape dataset below is what pins each one individually; this assertion
 * additionally covers them interacting in one file — a fixture where an
 * earlier registration's tokens could bleed into a later one's argument
 * positions.
 */
it('produces no violations on the compliant fixture', function () use ($routeRun): void {
    $file = $routeRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every flagged shape, at its exact line and column, all at the one violation
 * source. Columns are the first token of the *action argument*, not of the
 * registration, which is what makes a misread argument position visible here
 * rather than only in the count.
 *
 * Lines 7-13 sweep all seven verbs that carry their action second, so a verb
 * dropped from the enumeration falls out of this list. Lines 21-22 are the
 * Route::match pair, whose action sits third: reading position 2 for them
 * would inspect the URI and report nothing at all. Lines 49-52 name the action
 * instead of placing it, and their columns are what show the name was stripped
 * off the front of the argument rather than reported as its first token. Line
 * 56 puts a comment where the action starts, and line 59 spells the ::class
 * keyword in upper case: both report, so neither the comment skip nor the
 * keyword's casing can be dropped without this list changing.
 */
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
    ]);
});

/**
 * The standard's "very rare" carve-out is a judgement, so a flagged
 * registration must never fail a consumer's build. Asserted separately from
 * the tuples above, which would read identically if the sniff were raised to
 * error severity.
 */
it('reports warnings and never errors', function () use ($routeRun): void {
    expect($routeRun('failing.php')->getErrors())->toBe([])
        ->and($routeRun('failing.php')->getWarningCount())->toBe(23);
});

/**
 * Each near-miss on its own file. A shape that stopped being skipped reports
 * here even when the rest of passing.php would still be silent, which is the
 * difference between this and the whole-fixture assertion above.
 */
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
    'string holding an @ that names no controller' => ["Route::get('/a', 'support@example.com');"],
    'string action with no method' => ["Route::get('/a', 'PostController@');"],
    'chained builder registration' => ["Route::middleware('auth')->get('/a', [PostController::class, 'archive']);"],
]);

/**
 * Each flagged shape on its own file, asserting the method the message names.
 * The tuples above pin *where* the sniff reports; this pins *what* it read out
 * of the action, so an argument misread that still lands on a reportable token
 * — the methods array in a Route::match call, say — cannot pass both.
 */
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
]);

/**
 * The gate is decided from the path alone, so the same bytes have to fall
 * silent outside a routes/ directory. Driven from four paths rather than one,
 * because a single "outside" path cannot distinguish a working gate from a
 * sniff that had nothing to say about the file at all — the routes/ run below
 * is the other half of that pair.
 */
it('inspects a file only under a routes directory', function (string $directory, int $expected) use ($routeRun): void {
    expect($routeRun('failing.php', $directory)->getWarningCount())->toBe($expected);
})->with([
    'routes' => ['routes', 23],
    'nested under routes' => ['routes/admin', 23],
    'app' => ['app', 0],
    'app/Providers' => ['app/Providers', 0],
    'tests' => ['tests', 0],
]);

/**
 * The gate is a public property, so a consuming ruleset can retune it in XML.
 * Pinned through behaviour: the same file that is silent under app/ above
 * reports once the property names that path.
 */
it('honours a ruleset-configured routeFilePatterns', function (): void {
    $staged = stageFixtureOutsideTests(fixturePath('NonInvokableSpecialActionSniff', 'failing.php'), 'app');
    $configured = analyzeWithSniffs(
        [SPECIAL_ACTION],
        $staged,
        static function (object $sniff): void {
            $sniff->routeFilePatterns = ['*/app/*'];
        }
    );

    expect($configured->getWarningCount())->toBe(23);
});

/**
 * Piped input has no path for the gate to read, so the sniff has nothing to
 * say about it. Reachable only through a DummyFile: every fixture on disk has
 * a path.
 */
it('stays silent on input with no path', function (): void {
    $file = analyzeStdinSource(
        [SPECIAL_ACTION],
        "<?php\n\nRoute::get('/a', [PostController::class, 'archive']);\n"
    );

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
});
