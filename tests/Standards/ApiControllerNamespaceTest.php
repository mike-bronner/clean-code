<?php

/**
 * Tests the custom CleanCode.Routes.ApiControllerNamespace sniff (Routes:
 * Types (API / View), #66). Fixtures live in
 * tests/fixtures/ApiControllerNamespaceSniff/. The rule is detection-only, so
 * there is no autofixed fixture.
 *
 * The sniff compares two things: the API segment in a controller's declared
 * namespace, and the API segment in the path of the file it sits in. That
 * second half is why this directory holds more than the two flat fixtures. The
 * contract's passing.php and failing.php have fixed names *and* a fixed
 * location, and that location carries no Controllers segment — so they can
 * only ever exercise the namespace side. The path side needs fixtures whose
 * real paths carry that segment, which is where the nested trees under this
 * directory come from:
 *
 * - app/Http/Controllers/ — the API/ pair for a path below the controller root,
 *   and root-compliant.php for a file sitting directly on it;
 * - Controllers/ — two checkouts that put a second, decoy Controllers segment
 *   above the project, for the ancestor-anchoring cases.
 *
 * They are ordinary in-repo fixtures, covered by composer lint's fixtures
 * ignore pattern like every other one, and invisible to the contract sweep,
 * which only looks for the three fixed names.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const API_CONTROLLER_NAMESPACE = 'CleanCode.Routes.ApiControllerNamespace';

const API_CONTROLLER_NAMESPACE_MISSING = API_CONTROLLER_NAMESPACE . '.MissingApiNamespace';

const API_CONTROLLER_NAMESPACE_UNEXPECTED = API_CONTROLLER_NAMESPACE . '.UnexpectedApiNamespace';

const API_PATH_FIXTURES = 'app/Http/Controllers/API/';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(API_CONTROLLER_NAMESPACE);
});

/**
 * Every shape in passing.php stays silent, and each one pins a different
 * reason for that silence. Each line below is the `class` declaration itself —
 * the token the sniff reports at — not the comment introducing it:
 *
 * - line 12, `App\Http\Controllers\ReportController` — the compliant view
 *   controller. The sniff registers on it and finds both sides agreeing that
 *   this is not API; it is not silent for want of anything to look at.
 * - line 18, `ApiTokenController` — the class *name* names the API. Only
 *   namespace segments are compared, so this is left alone.
 * - line 26, `App\Http\Controllers\Reports\IndexController` — a segment below
 *   the controller root that is not API.
 * - line 36, `App\Services\API\Client` — an API segment with no controller
 *   root above it on either side. Without the controller-root gate this reads
 *   as an API-namespaced class at a non-API path, which is the
 *   UnexpectedApiNamespace violation.
 * - line 45, `Api\Http\Controllers\WebhookController` — an Api segment
 *   *above* the controller root. Only the segments below it count, so a
 *   vendor package rooted at Api is not every-file-is-API.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php sits at a path with no API segment, so every API-namespaced
 * controller in it is misplaced. Each line pins a distinct resolution:
 *
 * - line 9, the plain case inside a braced `App\Http\Controllers\API` block.
 * - line 21, the class *after* a `namespace\formatted()` call in the previous
 *   class's body. T_NAMESPACE is both the declaration keyword and the
 *   relative-name operator; the nearest T_NAMESPACE above this class is that
 *   operator, so without the sniff telling the two apart this class resolves
 *   to a namespace of `formatted()`, matches no controller root, and is not
 *   reported at all.
 * - line 29, `App\Http\Controllers\api\Reports` — the API grouping compared
 *   case-insensitively. Without that, a lowercase segment escapes.
 * - line 39, `App\Http\CONTROLLERS\API` — the *controller root* compared
 *   case-insensitively, which is a separate normalization from the one above
 *   and needs its own shape. Match the root literally and this namespace has
 *   no controller root, both sides fall to "not a controller", and the
 *   misplaced API controller is never reported.
 *
 * The column is 5 rather than 1 because braced namespace blocks indent their
 * class declarations; the violation is reported at the `class` keyword.
 */
it('flags every misplaced API namespace at its own line', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 21, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 29, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
        ['line' => 39, 'column' => 5, 'source' => API_CONTROLLER_NAMESPACE_UNEXPECTED],
    ]);
});

/**
 * The path side, reachable only from a fixture whose real path carries the API
 * segment. The compliant half has to be its own file: asserting silence is
 * only meaningful where nothing else in the file is speaking.
 */
it('leaves a controller whose namespace matches its API path alone', function (): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, API_PATH_FIXTURES . 'api-path-compliant.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The third path shape: a file directly on the controller root, no
 * subdirectory under it. The sniff keeps "no controller root in this path" and
 * "on the controller root, nothing below it" as different answers, and only
 * the second one describes a view controller in the ordinary Laravel layout.
 * Every other fixture here lands on one of the other two branches — the flat
 * pair carries no Controllers segment, the API/ pair carries an API segment
 * below it — so fold the bare root into "API path" and nothing else complains:
 * app/Http/Controllers/HomeController.php starts reporting MissingApiNamespace.
 */
it('leaves a controller sitting directly on the controller root alone', function (): void {
    $file = analyzeFixture(
        API_CONTROLLER_NAMESPACE,
        'app/Http/Controllers/root-compliant.php'
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * PHPCS hands the sniff a fully resolved absolute path, so every directory
 * above the project is part of what gets read — including whatever the checkout
 * happens to sit under. This package is distributed for other repositories to
 * require, so that location is not under its control.
 *
 * Two shapes pin the two halves of the defence, and each one is silent for a
 * different reason:
 *
 * - `Controllers/api/app/Http/Controllers/root-ancestor-compliant.php`, line 18
 *   — two `Controllers` segments in one path. The controller root is the one
 *   closest to the file, so the tail is empty. Anchor on the first instead and
 *   the tail becomes api/app/Http/Controllers, an API path, and this ordinary
 *   view controller reports MissingApiNamespace.
 * - `Controllers/my-app/app/Http/Resources/Api/UserResource.php`, line 18 — one
 *   `Controllers` segment, and it is the ancestor. The declared namespace
 *   carries no controller root, which places the class outside one whatever the
 *   path says. Let the path speak anyway and the tail is
 *   my-app/app/Http/Resources/Api: MissingApiNamespace on a resource class.
 */
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

/**
 * The reverse violation, at the same API path:
 *
 * - line 9, `App\Http\Controllers` — a controller namespace that simply lacks
 *   the API segment its location carries.
 * - line 18, a class in the global namespace. The location alone identifies it
 *   as a controller, which is the branch where the namespace side contributes
 *   no segments at all.
 */
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

/**
 * Moving the file and rewriting its declared namespace are both valid ways to
 * reconcile the two halves, and which one is right depends on the application's
 * layout rather than on anything in the file — so the sniff never offers a fix.
 *
 * Both fixtures are checked, because the two violation codes are raised at
 * separate call sites: asserting only on failing.php would leave
 * MissingApiNamespace free to hand out a fix nobody wrote.
 */
it('marks no violation fixable', function (string $fixture, int $expected): void {
    $file = analyzeFixture(API_CONTROLLER_NAMESPACE, $fixture);

    expect($file->getErrorCount())->toBe($expected)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    ['failing.php', 4],
    [API_PATH_FIXTURES . 'api-path-missing-namespace.php', 2],
]);

/**
 * Piped input with no --stdin-path gives PHPCS the file name STDIN, so there is
 * no location for the namespace to disagree with — an editor linting a buffer
 * that way would otherwise get every API controller reported as misplaced.
 *
 * The same bytes are run twice, at a real path and with none, so the silence is
 * pinned to the missing path rather than to the source having nothing the sniff
 * reacts to: at a real path this exact source is a violation.
 */
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
