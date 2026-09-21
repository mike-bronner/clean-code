<?php

/**
 * Tests the custom CleanCode.Routes.DisallowNonResourceRoutes sniff (Routes:
 * Conventions (Do / Do Not), #65, partial enforcement per #248). Fixtures live
 * in tests/fixtures/DisallowNonResourceRoutesSniff/: the compliant resource
 * routes, the wrapping modifiers and every near-miss shape in passing.php, the
 * flagged verb registrations in failing.php. The rule is detection-only, so
 * there is no autofixed fixture.
 *
 * The sniff decides what to inspect from the file's own path, and the fixture
 * contract fixes both the fixture names and the directory they sit in —
 * tests/fixtures/DisallowNonResourceRoutesSniff/ holds no `routes` segment, so
 * it matches no default glob and the fixtures report nothing where they live.
 * That is also why the sniff is out of the generic contract sweep, which drives
 * each fixture in place: every assertion about the sniff's own behaviour runs
 * against a copy staged under a real `routes` directory outside the repository
 * ($routeRun below), and the gate itself is pinned separately by the
 * inspects-nothing-outside-a-route-file test, which requires the silence to come
 * from the path rather than from the sniff having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const NON_RESOURCE_ROUTES = 'CleanCode.Routes.DisallowNonResourceRoutes';

const NON_RESOURCE_ROUTES_WARNING = NON_RESOURCE_ROUTES . '.Found';

/**
 * The fixture directory this sniff owns, resolved once so the fixture path and
 * the shipped-binary run below cannot drift onto different files.
 */
const NON_RESOURCE_ROUTES_FIXTURES = 'DisallowNonResourceRoutesSniff';

// Fixtures are copied into a `routes` directory outside the repository before
// processing, because the sniff decides what to inspect from the file's path
// alone. The staged copies are removed by the afterEach() hook in tests/Pest.php.
$routePath = static fn (string $fixture): string => stageFixtureOutsideTests(
    fixturePath(NON_RESOURCE_ROUTES_FIXTURES, $fixture),
    'routes'
);

$routeRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
    [NON_RESOURCE_ROUTES],
    $routePath($fixture)
);

/**
 * Every warning failing.php owes, at the line and column of the verb token
 * itself. Shared by the in-process assertions and the configurable-property
 * one, which asserts the same verdicts arrive through a retuned glob.
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
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

/**
 * The compliant shapes and every near-miss stay silent. Each group pins one of
 * the sniff's decisions, and a false positive on any of them makes the rule
 * unusable in a real route file:
 *
 * - lines 3-6, `Route::resource()`, `apiResource()`, `resources()` and
 *   `apiResources()` — the shapes the standard asks for. They are absent from
 *   the watched verb list, which is what keeps them quiet.
 * - line 7, `Route::fallback()` — the no-match handler. Deliberately outside
 *   the watched list: it registers no resource and has no RESTful controller
 *   to point at.
 * - lines 8-10, `Route::group()` wrapping a compliant resource route — a
 *   modifier registers nothing on its own, and the sniff must reach the
 *   nested call on line 9 without flagging the wrapper.
 * - lines 11-15, `Route::middleware()`, `prefix()`, `name()`, `domain()` and
 *   `controller()` — the remaining wrapping modifiers, each once.
 * - lines 17-19, `Route::getRoutes()`, `postProcessor()`, `matchedRoute()` —
 *   names that merely start with a watched verb. The comparison is on the
 *   whole method name, not a prefix match.
 * - lines 21-22, `Router::get()` and `ApiRoute::get()` — receivers whose
 *   trailing segment merely contains or extends `Route`. Without the
 *   whole-segment comparison these read as the facade.
 * - lines 23-25, `$router::get()`, `$router->get()` and `static::get()` — a
 *   variable receiver, an instance call, and a late-static-binding receiver.
 *   None is a name token before `::`. They are silent either way, and deleting
 *   the sniff's RECEIVER_TOKENS check changes no result here (verified by
 *   mutation — the suite stays green without it), because no such token's
 *   content can equal `route`. That check is a type guard rather than a
 *   behavioural branch, and its sibling — the trailing-segment split in
 *   isRouteFacade() — is unpinnable for the same reason: PHP_CodeSniffer 3.x
 *   undoes PHP 8's qualified-name tokens, so the receiver token always carries
 *   the trailing segment already and splitting it is a no-op. Both exist for a
 *   future PHPCS that stops undoing it, and both are stated plainly here rather
 *   than left to imply coverage no fixture can give them.
 * - line 27, `Route::GET` with no argument list — a class-constant read. This
 *   is what pins the open-parenthesis half of registersRoute(): the name and
 *   the receiver both match, and only the missing `(` separates it from a
 *   registration.
 * - line 28, `Route::get(...)` — PHP 8.1's first-class-callable syntax builds
 *   a Closure and registers nothing. The paired case is failing.php line 18,
 *   `Route::get(...$definition)`, an argument spread that *does* register and
 *   is flagged; between them they pin both halves of isFirstClassCallable().
 * - lines 29-30, `Route::middleware('auth')->get()` and
 *   `Route::prefix('admin')->post()` — the fluent-chained verb calls the
 *   docblock records as a known false negative. The verb follows `->`, not
 *   `::`, so it is invisible to this heuristic. Asserted here so the
 *   documented boundary is pinned rather than merely claimed: a sniff that
 *   grew to catch them would fail this test and have to say so.
 */
it('produces no violations on the compliant fixture', function () use ($routeRun): void {
    $file = $routeRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every verb registration is flagged once, anchored at the verb token rather
 * than at the receiver or the statement:
 *
 * - lines 3-10, the eight watched verbs in lowercase, one per line. `match`
 *   is among them and is the reason the verb is matched on token *content*:
 *   PHP_CodeSniffer re-labels the reserved word after `::` as T_STRING, and a
 *   sniff keyed on T_MATCH would miss it.
 * - line 12, `Route::GET()` — PHP method names are case-insensitive, so the
 *   comparison is too. Column 8 is the same as its lowercase siblings above,
 *   which is what makes this more than a duplicate of line 3: the
 *   non-lowercase spelling resolves to the same violation at the same anchor.
 * - line 13, `ROUTE::get()` — the receiver half of the same rule. PHP class
 *   names are case-insensitive too, so a consuming codebase's spelling of the
 *   facade must not matter, and the sniff lower-cases the receiver's trailing
 *   segment before comparing. The receiver is the same length as the canonical
 *   `Route`, so the anchor stays at column 8 and only the casing differs from
 *   line 3. A comparison against a literal `Route` — or one folded the wrong
 *   way — leaves this line unreported.
 * - line 15, `\Route::post()` at column 9 and line 16,
 *   `Illuminate\Support\Facades\Route::put()` at column 35 — the qualified
 *   spellings of the same facade. The receiver is compared on its trailing
 *   segment, so both report, and their columns record that the anchor follows
 *   the receiver rather than opening the statement.
 * - line 18, `Route::get(...$definition)` — an argument spread, not a
 *   first-class callable, so it registers a route and is flagged. Its
 *   counterpart is passing.php line 28.
 * - line 21, a verb call nested inside a `Route::group()` closure, at column
 *   12 — the shape real route files are full of. The group itself on line 20
 *   reports nothing.
 */
it('flags every violation at its own line and column', function () use ($routeRun, $expectedWarnings): void {
    $file = $routeRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe($expectedWarnings());
});

/**
 * The message names the offending verb, so a developer reading the report knows
 * which registration to reconsider. Line 12's `GET` is asserted because the
 * comparison is case-insensitive while the message is not: the source spelling
 * has to survive into the output rather than the lowercased copy the check
 * works from.
 */
it('names the offending verb in the warning message', function () use ($routeRun): void {
    $warnings = $routeRun('failing.php')->getWarnings();

    expect($warnings[3][8][0]['message'])->toContain('Route::get()')
        ->and($warnings[12][8][0]['message'])->toContain('Route::GET()');
});

/**
 * The filename gate is load-bearing, not decoration.
 *
 * Three runs over the same bytes, because no one of them is enough on its own:
 * the two silent runs would pass just as well against a sniff that never fires
 * at all, and the reporting run alone says nothing about the gate. Together they
 * say the diagnostics turn on the path and nothing else.
 *
 * The in-repo run is where the fixture actually lives, under
 * tests/fixtures/ — the path the generic contract sweep would have driven, and
 * the reason this sniff is held out of it. The staged run outside a `routes`
 * directory covers the same absence at a path the repository does not control,
 * so the silence cannot be coming from some other tests/ scoping.
 */
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

/**
 * The gate's globs are a public sniff property, as the standard's doc
 * advertises. The same bytes at the same path are run twice: under the shipped
 * default a copy staged outside any `routes` directory is silent, and once the
 * property names that directory the full fourteen warnings arrive. A property
 * that was ignored would leave both runs identical and fail the second half.
 *
 * The replacement glob is derived from the staged path rather than written out,
 * so the test cannot pass by accident on a staging root that happened to match
 * the shipped default.
 */
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

/**
 * Pins the two severity decisions the issue makes explicitly: the rule warns
 * rather than errors, because the standard itself allows a rare special-action
 * exception a sniff cannot recognise; and nothing is fixable, because turning a
 * verb route into a resource route means writing the seven RESTful actions.
 */
it('reports detection-only warnings', function () use ($routeRun): void {
    $file = $routeRun('failing.php');

    expect($file->getWarningCount())->toBe(14)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The same verdict through the shipped, installed package.
 *
 * Every test above drives PHP_CodeSniffer in process through ConfigDouble, which
 * supplies the registration Composer would have supplied — so a package that
 * never registered itself with the installed standards passes all of them. This
 * one executes the real vendor/bin/phpcs as a separate process from outside the
 * package, against CleanCode/ruleset.xml, the file a consumer points --standard at. The
 * shared sweep in tests/Contract/ShippedPackageSmokeTest.php cannot reach this
 * sniff: it drives each fixture where it lives, under tests/, where the default
 * glob matches nothing.
 *
 * Asserted in the same paired shape as the gate test above rather than on the
 * positive half alone:
 *
 * - the copy staged under `routes` reports all fourteen, every message under
 *   this sniff's own code and at WARNING severity, at status 1 — violations,
 *   none of them fixable, which is what this detection-only rule owes. Status 2
 *   would mean phpcbf had been offered a fix, and 3 is what a broken install
 *   exits with.
 * - the in-repo copy of the same bytes reports nothing and exits 0, so the
 *   reporting half cannot be coming from a run that ignores the gate.
 * - passing.php staged the same way reports nothing and exits 0 — the negative
 *   control, without which a shell-out that always reported would satisfy the
 *   first.
 */
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
