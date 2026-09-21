<?php

/**
 * Tests the custom CleanCode.Controllers.NoCustomActions sniff (Controllers:
 * No Business Logic, #48/#141). Fixtures live in
 * tests/fixtures/NoCustomActionsSniff/.
 *
 * The sniff carries one token-visible slice of an otherwise Tier 3 standard:
 * a controller is RESTful or invokable, so a public method outside the seven
 * Laravel resource actions, `__construct`, `__invoke`, and the configured
 * allowlist is a custom action.
 *
 * Warnings, not errors. The match is on a naming convention and the sniff
 * cannot prove a class is routed, so a misread must not fail a build.
 *
 * The rule is detection-only: removing a custom action means moving it into
 * its own controller and rewriting the routes that reach it, which is an
 * architectural change with no mechanical rewrite. There is no autofixed
 * fixture.
 *
 * CleanCode/ruleset.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const NO_CUSTOM_ACTIONS = 'CleanCode.Controllers.NoCustomActions';

const NO_CUSTOM_ACTIONS_WARNING = NO_CUSTOM_ACTIONS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_CUSTOM_ACTIONS);
});

/**
 * The compliant fixture is silent, and so is everything the sniff must not
 * police:
 *
 * - lines 3-52, `UserController` declaring all seven resource actions
 *   (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) plus
 *   `__construct` (line 5), and two non-action helpers that are `protected`
 *   (line 44) and `private` (line 48). A routed action has to be public, so
 *   neither helper is one.
 * - lines 54-60, `PublishPostController` — the invokable half of the rule,
 *   a single `__invoke`.
 * - lines 62-67, `UserService` with a custom public method. The class name
 *   does not end in `Controller`, so the sniff never looks at it.
 * - lines 69-75, `ControllerFactory` — a near miss that *contains*
 *   "Controller" without ending in it. A substring match would report
 *   `build()` on line 71.
 * - lines 77-80, `interface DownloadController`, and lines 82-87,
 *   `trait ArchiveController`. Both names end in `Controller`, and both
 *   declare a public method the sniff would otherwise flag; the sniff
 *   registers T_CLASS alone, so neither is examined. An interface method is
 *   a contract, not a route, and a trait's methods belong to whichever class
 *   mixes them in.
 * - lines 89-100, `LegacyCaseController` declaring `Index()` and `DESTROY()`.
 *   PHP method names are case-insensitive, so both are resource actions
 *   however they are spelled. This is what makes the method-name lowercasing
 *   load-bearing: every other fixture spells the resource actions in the
 *   canonical lower case, so without these two the comparison could drop its
 *   strtolower() and the suite would stay green.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Four custom actions, each a different declaration shape, reported on the
 * method name:
 *
 * - line 10, `export()` — an ordinary public method.
 * - line 15, `warmCache()` — `public static`. Static or not, it is public and
 *   outside the resource set.
 * - line 19, `approve()` — no visibility modifier at all. PHP defaults that
 *   to public, so a sniff reading only an explicit `public` keyword would
 *   miss it.
 * - line 36, `handle()` — `abstract public`, so no method body. The
 *   declaration alone decides this.
 *
 * The same fixture pins the three silences that share the file: `index()` on
 * line 5 (a resource action), `formatRow()` on line 23 (`protected`), and
 * `columns()` on line 28 (`private`). Because the assertion is an exact list,
 * a sniff that started reporting any of them fails here.
 */
it('flags every custom public action in a controller', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 10, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 15, 'column' => 19, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 19, 'column' => 5, 'source' => NO_CUSTOM_ACTIONS_WARNING],
            ['line' => 36, 'column' => 21, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

/**
 * The message names the method, so a report over a whole application says
 * which action to open.
 */
it('names the offending method in the warning message', function (): void {
    $warnings = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php')->getWarnings();

    expect($warnings[10][12][0]['message'])->toContain('export()');
});

/**
 * Only a method the controller itself declares is an action. Membership is
 * decided by the innermost enclosing scope, and this fixture puts two
 * declarations that a positional scan would swallow inside resource actions:
 *
 * - line 8, `format()` — a public method of an anonymous class built inside
 *   `index()`. Its innermost condition is the anonymous class.
 * - line 19, `normalizeInventoryPath()` — a named function declared inside
 *   `store()`. PHP defaults it to public, and its innermost condition is
 *   `store()` itself.
 *
 * Dropping the innermost-condition check reports both, so this exact list is
 * what pins it. `reconcile()` on line 31 is the controller's own custom
 * action and keeps the assertion two-sided: a sniff that had fallen silent
 * altogether fails here too.
 *
 * The closure assigned on line 24 takes no part in the check — PHPCS
 * tokenizes it as T_CLOSURE, which the scan never looks for. It sits in the
 * fixture as the third shape a reader expects to see covered, not as
 * evidence of the condition check.
 */
it('ignores declarations nested inside an action', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'nested-declarations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 31, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

/**
 * The allowlist is a public sniff property, so a ruleset can admit the
 * framework hooks a controller has to declare — Laravel 11's
 * `HasMiddleware::middleware()` being the case the standard's doc names. One
 * fixture pins both directions: `middleware()` is reported under the shipped
 * empty default and silent once the property names it. A property that was
 * ignored would leave both runs identical and fail the second assertion.
 *
 * The configured name is spelled in upper case against a lowercase method,
 * because PHP method names are case-insensitive and a consuming ruleset
 * should not have to match the declaration's casing.
 */
it('exposes a configurable allowlist', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'configured.php');

    expect(warningTuples($file))->toBe([
        ['line' => 10, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
    ]);

    $configured = analyzeFixture(
        NO_CUSTOM_ACTIONS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->allowedMethods = ['MIDDLEWARE'];
        }
    );

    expect($configured->getErrors())->toBe([])
        ->and($configured->getWarnings())->toBe([]);
});

/**
 * A `class` keyword with no name after it — one of the half-written shapes
 * PHPCS hands a sniff mid-edit. There is no class name to test the
 * `Controller` suffix against, so the sniff passes over the file. This is
 * what reaches the `$className === null` guard; anonymous classes cannot,
 * because PHPCS gives them their own T_ANON_CLASS token, which this sniff
 * never registers for.
 */
it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The other half-written shape: a closed controller whose last member is a
 * bare `public function` with no name yet (line 10). It is public, and its
 * innermost condition is the class, so it reaches the name lookup — which
 * returns null, and there is nothing to judge. `export()` on line 5 is still
 * reported, so the fixture proves the sniff stepped over the unfinished
 * declaration rather than bailing out of the class.
 *
 * The `$method === null` guard is load-bearing here, not defensive: without
 * it the null name reaches isAllowed(string $method) and the run dies with a
 * TypeError.
 */
it('steps over a method declaration with no name', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 5, 'column' => 12, 'source' => NO_CUSTOM_ACTIONS_WARNING],
        ]);
});

/**
 * An unterminated class body. PHPCS resolves no scope for it — the T_CLASS
 * token gets neither a scope_opener nor a scope_closer, and every token
 * inside carries an empty conditions list — so `publish()` on line 5 resolves
 * to no class at all and the sniff reports nothing.
 *
 * Silence is the answer this fixture pins, and it is deliberate rather than
 * incidental: with the file's structure unresolved, attributing a method to a
 * class the tokenizer never closed would be a guess. The behaviour is
 * recorded in the sniff's own docblock and in the standard's doc, so it is a
 * disclosed boundary rather than an unnoticed hole.
 */
it('reports nothing on an unterminated class body', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the detection-only decision at the severity the standard speaks in:
 * warnings, never errors, and nothing fixable. Extracting a custom action
 * into its own controller rewrites the routes that reach it, so there is no
 * mechanical fix to offer.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(NO_CUSTOM_ACTIONS, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
