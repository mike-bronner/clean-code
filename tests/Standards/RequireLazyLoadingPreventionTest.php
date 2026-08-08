<?php

/**
 * Tests the custom CleanCode.Models.RequireLazyLoadingPrevention sniff
 * (Models: Eager Loading, #74/#154). Fixtures live in
 * tests/fixtures/RequireLazyLoadingPreventionSniff/.
 *
 * The sniff is an absence check: a watched provider class that never enables
 * Laravel's lazy-loading safety check earns one warning on its class
 * declaration. That shape makes a silent sniff indistinguishable from a
 * satisfied one, so every "no violations" assertion here is paired with a
 * fixture that does warn — the negative alone would pass just as well against
 * a sniff that never fires.
 *
 * The rule is detection-only: writing the call into a service provider is a
 * change to application bootstrapping, not a formatting fix, so there is no
 * autofixed fixture.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */

declare(strict_types=1);

const LAZY_LOADING = 'CleanCode.Models.RequireLazyLoadingPrevention';

const LAZY_LOADING_WARNING = LAZY_LOADING . '.Missing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LAZY_LOADING);
});

/**
 * The canonical provider stays silent, and so does everything the sniff
 * must not police:
 *
 * - lines 3-14, `AppServiceProvider` calling
 *   `Model::preventLazyLoading(! $this->app->isProduction());` in `boot()`
 *   — the shape the standard's doc recommends.
 * - lines 16-22, `RouteServiceProvider` — a sibling provider with no
 *   safety check at all. Every Laravel app and package ships several of
 *   these, and none is expected to enable the check, which is why the
 *   sniff keys off the class name rather than `extends ServiceProvider`.
 * - lines 24-27, a model carrying a populated `$with` property. That is a
 *   violation of the *other* slice of this standard, owned by
 *   CleanCode.Models.DisallowAlwaysOnEagerLoading (#153), and must not be
 *   reported by this sniff, whose subject is the provider alone.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * `Model::shouldBeStrict()` turns preventLazyLoading() on as part of
 * Laravel's strict mode, so a provider using it has enabled the check and
 * must not be flagged. This pins the second entry of the accepted-method
 * list; dropping it from the sniff turns this fixture into a violation.
 */
it('accepts strict mode as enabling the check', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'strict-mode.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * `boot()` delegating to a private `configureModels()` helper is a common
 * provider idiom, and the check is switched on either way. The sniff
 * therefore accepts the call anywhere in the class body; a version that
 * searched only `boot()`'s scope would report this file.
 */
it('accepts the call anywhere in the class body', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'delegated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A provider with a real `boot()` that never enables the check earns
 * exactly one warning, reported on the class declaration (line 3) rather
 * than on any single statement — the defect is the absence of a call, so
 * it has no line of its own.
 */
it('flags a provider without the safety check on its class declaration', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [LAZY_LOADING_WARNING],
        ]);
});

/**
 * The message names the class that is missing the call, so a report over a
 * whole application says which provider to open.
 */
it('names the provider class in the warning message', function (): void {
    $warnings = analyzeFixture(LAZY_LOADING, 'failing.php')->getWarnings();

    expect($warnings[3][1][0]['message'])->toContain('AppServiceProvider');
});

/**
 * Five shapes that mention the method without calling it statically, all
 * in one provider that must still be reported. Each pins one condition of
 * the detection, and a sniff missing any of them would fall silent here:
 *
 * - line 7, the call commented out — a T_COMMENT, never a T_STRING.
 * - line 8, the name inside a string literal.
 * - line 9, `$this->preventLazyLoading()` — an object-operator call. The
 *   Laravel API is static only, so the operator before the name has to be
 *   `::`.
 * - line 10, `preventLazyLoading()` as a plain function call — no operator
 *   before the name at all.
 * - line 11, `Model::preventLazyLoading` with no argument list — a
 *   constant fetch, not a call, which is why the token after the name has
 *   to open a parenthesis.
 */
it('rejects shapes that only mention the method', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'near-miss.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        3 => [LAZY_LOADING_WARNING],
    ]);
});

/**
 * The search is bounded by the provider's own class body. Here a *second*
 * class in the same file (line 11) does enable the check while the watched
 * one (line 3) does not, so the watched class is still reported. A
 * file-wide search would report nothing.
 */
it('scopes the search to the watched class body', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'scoped.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        3 => [LAZY_LOADING_WARNING],
    ]);
});

/**
 * The watched class names are a public sniff property, so an application
 * that boots the check from another provider can point the sniff at it.
 * One fixture pins both directions: `ModelServiceProvider` without the
 * call is silent under the shipped default and reported once the list
 * names it. A property that was ignored would leave both runs identical
 * and fail the second assertion.
 *
 * The configured name is spelled in lower case against a PascalCase
 * class, because PHP class names are case-insensitive and a consuming
 * ruleset should not have to match the declaration's casing.
 */
it('exposes a configurable watched-provider list', function (): void {
    expect(analyzeFixture(LAZY_LOADING, 'configured.php')->getWarnings())->toBe([]);

    $configured = analyzeFixture(
        LAZY_LOADING,
        'configured.php',
        static function (object $sniff): void {
            $sniff->serviceProviderClasses = ['modelserviceprovider'];
        }
    );

    expect(violationSourcesByLine($configured->getWarnings()))->toBe([
        3 => [LAZY_LOADING_WARNING],
    ]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so the fixture ends with
 * `Model::preventLazyLoading` and nothing after it — no argument list, no
 * closing braces. The sniff has to pass over the half-written call rather
 * than fall over or accept it, and the class is reported because the check
 * genuinely is not enabled yet.
 *
 * With no token of any kind after the name, the lookahead for the opening
 * parenthesis returns false. The `$afterPtr !== false` guard that reads as
 * what handles this is in fact defensive only, and removing it changes no
 * result: PHP resolves `$tokens[false]` to `$tokens[0]`, the open tag,
 * which fails the parenthesis comparison anyway. It is kept for saying so
 * outright instead of leaning on that coercion. Stated here because no
 * fixture can pin it — this test covers the truncated call, not the guard.
 */
it('handles a truncated call without falling over', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'truncated.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        3 => [LAZY_LOADING_WARNING],
    ]);
});

/**
 * Both hops of the detection step over whitespace and comments, so a call
 * written across them is still a call. `spaced.php` gives each hop its own
 * provider, and each provider is watched on its own run, so neither assertion
 * can cover for the other.
 *
 * This run covers the lookback for the `::`, on line 7, where a block comment
 * sits between the double colon and the method name. Reading the token
 * immediately before the name would land on that comment, dismiss a genuine
 * call, and warn on a provider that does enable the check. The second
 * provider is not in the shipped watched list, so it takes no part here.
 */
it('skips comments between the double colon and the method name', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'spaced.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The other hop, pointed at the second provider in the same fixture: the
 * lookahead for the opening parenthesis, on line 15, where a block comment
 * sits between the method name and its argument list. Reading the token
 * immediately after the name would find that comment instead of the `(` and
 * take the call for a constant fetch.
 *
 * `AppServiceProvider` leaves the watched list for this run, so nothing here
 * rests on the lookback the test above pins.
 */
it('skips comments between the method name and its argument list', function (): void {
    $file = analyzeFixture(
        LAZY_LOADING,
        'spaced.php',
        static function (object $sniff): void {
            $sniff->serviceProviderClasses = ['ModelServiceProvider'];
        }
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A `class` keyword with no name after it — the other half-written shape
 * PHPCS hands a sniff mid-edit. There is no class name to match against
 * the watched list, so the sniff passes over the file rather than falling
 * over on it. This is what reaches the `$name === null` guard; anonymous
 * classes cannot, because PHPCS gives them their own T_ANON_CLASS token,
 * which this sniff never registers for.
 */
it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the detection-only decision: enabling the safety check is a change
 * to how the application boots, so no violation is auto-fixable.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(LAZY_LOADING, 'failing.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
