<?php

/**
 * Tests the custom CleanCode.Routes.DisallowClosureRoutes sniff, the
 * enforceable slice of Routes: Conventions (Do / Do Not) (#65) —
 * docs/standards/routes-conventions-do-do-not.md.
 *
 * The rule is detection-only. Replacing a closure action means writing a
 * controller and choosing where it lives, which is not a mechanical rewrite,
 * so there is no autofixed fixture and the detection-only test pins that.
 *
 * One assertion here pins a design choice rather than a line of code, and it
 * was measured rather than assumed. A sniff that registered on T_CLOSURE/T_FN
 * and walked *outward* through `nested_parenthesis` to its enclosing call is
 * the obvious alternative implementation, and it double-reports: a closure
 * body opens no parenthesis of its own, so a closure declared inside the
 * action closure carries the same innermost opener as the action itself.
 * Rewriting the sniff that way and re-running this file fails exactly two
 * assertions — the position test, and 'reports a route action once, not once
 * per closure inside it' on its second dataset. No smaller mutation reddens
 * that dataset, which is why it is here.
 *
 * The group exclusion is the other property with no guard behind it: it rests
 * entirely on `group` being absent from the sniff's verb list, so passing.php
 * carries all three spellings of a group callback and adding `group` back to
 * the list fails four of the assertions below.
 */

declare(strict_types=1);

const DISALLOW_CLOSURE_ROUTES = 'CleanCode.Routes.DisallowClosureRoutes';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_CLOSURE_ROUTES);
});

/**
 * passing.php is the sniff's whole silence contract, and every block in it is
 * a near miss rather than merely the absence of a closure:
 *
 * - Every registration verb the sniff knows, each with a compliant action: an
 *   array action, the legacy `'Controller@method'` string, and an invokable
 *   controller's `::class`. The verbs are all here because the list is
 *   hand-maintained, and a verb dropped from it would otherwise fail silently.
 * - Three group callbacks — `Route::group()`, a chained `->group()`, and a
 *   chained `->group()` taking an arrow function. A group callback is not a
 *   route action and caches fine. Nothing guards it: `group` is simply not a
 *   registration verb, so these three are what prove the exclusion holds
 *   through both the static and the chained resolution paths.
 * - A chained verb with a compliant action (`->get('/account', [...])`). The
 *   chain resolves to the facade, so this call *is* examined; it is here to
 *   show the chain walk reaching a clean verdict rather than bailing out.
 * - Two closures one level below the argument list — inside a nested call and
 *   inside an array literal. Only a direct argument is a route action. Each is
 *   preceded by a comma of its own, which is what makes them discriminating: a
 *   scan that did not step over the nested construct would read that comma as
 *   this call's argument boundary and flag the closure behind it.
 * - A chained `->missing()` callback: a genuine closure on a route, on a
 *   method that registers nothing.
 * - `Cache::get('users', fn () => [])`. The same verb name, the same closure
 *   position, a different class. This is the one shape that would still flag
 *   if the class-name check were dropped entirely, so it carries the whole
 *   weight of that check on this fixture.
 * - `$router->get(…)` and `$this->get(…)`, both with closures. Neither has a
 *   `Route::` to resolve to — the first is the documented variable-router
 *   boundary, the second has no preceding call for the chain walk to hop over.
 * - `get()` and `match()` declared as methods. The token before the name is
 *   `function`, so neither resolves to the facade.
 * - `Route::redirect`, `Route::view` and `Route::resource`. Real facade calls
 *   on methods outside the verb list. `Route::resource` takes a controller, so
 *   the three are not silent merely for having no action-shaped argument.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exact position of every violation in failing.php.
 *
 * The column matters as much as the line: the violation is reported on the
 * closure token itself, not on the route call, so a `static function` reports
 * at `function` (line 13, column 25) rather than at `static` (column 18), and
 * a named argument reports past its label (line 50, column 26 rather than 18).
 * Reporting the enclosing call instead would keep every line number and lose
 * both positions.
 *
 * Line 29 is `Route::match(['get', 'post'], '/h', …)`: its action is the third
 * argument, behind an array literal whose own commas must not be read as this
 * call's argument boundaries. Line 34 is `Route::fallback(…)`, whose action is
 * the first argument. Between them they pin that the scan finds the action
 * wherever it sits rather than at a fixed index.
 *
 * Line 39 is the fully qualified `\Illuminate\Support\Facades\Route::get`, and
 * lines 44-45 reach the verb through one and two chained builder calls.
 *
 * Line 77 is `match` reached through a chain. `match` is a PHP keyword, and it
 * can sit in the verb list at all only because PHPCS hands it back as a plain
 * T_STRING when it names a method — so both spellings that rely on that are
 * here, the static one on line 29 and the chained one on line 77.
 */
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

/**
 * The nine verbs, each named in its own message rather than merely counted.
 *
 * Without this, a sniff that had quietly dropped a verb from its list would
 * still satisfy the position test: that verb's block in failing.php would
 * simply report nothing and the remaining lines would shift out of the
 * expectation together.
 */
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

/**
 * A route action is reported once, however many closures the statement holds.
 *
 * failing.php line 57 is the `Route::get` inside a `Route::group` callback:
 * the group callback is compliant and the inner action is not, so the
 * statement owns exactly one violation. Line 64 is a route action whose own
 * body declares an arrow function — one action, one violation, even though the
 * statement contains two closures.
 *
 * Both are counted through the whole statement's line range rather than on the
 * reported line alone, so a second violation landing anywhere in the statement
 * would fail this.
 */
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

/**
 * Error severity, not warning. The standard's "Do Not" is unconditional and
 * the breakage is mechanical — `php artisan route:cache` fails outright — so
 * unlike the two heuristic route sniffs (#248, #249) this one fails a build.
 * Asserting the empty warning list as well is what stops a future `addWarning`
 * from satisfying the position test above.
 */
it('reports at error severity under its own source', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    $sources = array_values(array_unique(array_column(violationTuples($file), 'source')));

    expect($file->getWarnings())->toBe([])
        ->and($sources)->toBe([DISALLOW_CLOSURE_ROUTES . '.ClosureAction']);
});

/**
 * Detection only. Writing the controller a closure action should have been is
 * a design decision, not a mechanical rewrite, so nothing here is fixable and
 * the fixture directory carries no autofixed.php.
 */
it('offers no fix for a closure action', function (): void {
    $file = analyzeFixture(DISALLOW_CLOSURE_ROUTES, 'failing.php');

    expect(array_unique(violationFixableFlags($file)))->toBe([false])
        ->and(file_exists(__DIR__ . '/../fixtures/DisallowClosureRoutesSniff/autofixed.php'))->toBeFalse();
});
