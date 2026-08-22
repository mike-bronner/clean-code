<?php

/**
 * Tests the custom CleanCode.Naming.RedundantNamespaceSuffix sniff (Classes:
 * Class Naming, #27). Fixtures live in
 * tests/fixtures/RedundantNamespaceSuffixSniff/. The rule is detection-only, so
 * there is no autofixed fixture.
 *
 * passing.php and failing.php are files of braced namespace blocks, which is
 * what lets one file put a declaration under many different namespaces — the
 * sniff reads the declared namespace and nothing else, so a namespace per shape
 * is the whole of what a fixture has to vary. The reported column is 5 rather
 * than 1 in those two because a braced block indents its declarations; the
 * violation is anchored on the declaration keyword either way.
 *
 * file-scoped-namespace.php is the exception, and has to be: one file can carry
 * only one file-scoped namespace, so the form real application code writes
 * cannot be exercised inside the braced fixtures at all.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const REDUNDANT_NAMESPACE_SUFFIX = 'CleanCode.Naming.RedundantNamespaceSuffix';

const REDUNDANT_NAMESPACE_SUFFIX_FOUND = REDUNDANT_NAMESPACE_SUFFIX . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REDUNDANT_NAMESPACE_SUFFIX);
});

/**
 * Every shape in passing.php stays silent, and each one pins a different
 * reason for that silence:
 *
 * - `App\Services\Billing` — the standard's own fix for the first failing
 *   shape. It is not silent for want of anything to look at.
 * - `App\Services\Billing\BillingGateway` — the name opens with an ancestor's
 *   name instead of ending in it. The standard is about a redundant *suffix*,
 *   so a prefix echo is out of scope by design; match anywhere in the name and
 *   this is a violation.
 * - `App\Ads\Squad` — the word-boundary rule. `Squad` ends in the letters of
 *   the singular `Ad`; drop the boundary check and an ordinary English word
 *   becomes a violation.
 * - `App\Notifications\Ordernotification` — the accepted cost of that same
 *   boundary: the suffix is there but runs on in lower case, so no boundary
 *   marks it. Documented as a limit rather than closed, because closing it is
 *   the same change that flags `Squad`.
 * - `App\Http\Controllers\UserProfile` — two ancestor segments, neither
 *   repeated. The sniff walks every ancestor, so this pins that walking them
 *   does not make everything match.
 * - `App\Application` — a declaration directly on the application root, with
 *   no folder below it whose name it could repeat. Feed the root itself in as
 *   a candidate and this is a violation.
 * - `Tests\Services\PaymentService` — the identical shape to the first failing
 *   fixture, one namespace root over. Drop the root gate and it is reported —
 *   which is exactly the rename PHPUnit's `Test` suffix and this package's own
 *   `Sniff` suffix could not accept.
 * - `Vendor\App\Repositories\UserRepository` — an `App` segment that does not
 *   head the namespace. PSR-4 maps the *prefix* onto app/, so this is an
 *   installed package's layout, not this project's app folder.
 * - `App\Factories\Anonymous` and the anonymous class in its body — T_ANON_CLASS
 *   is not registered, and an anonymous class has no name to repeat anything
 *   with.
 * - `GlobalService` in the global namespace block — no namespace, so nothing
 *   says where the class is filed.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php, one violation per declaration, each line a distinct reason the
 * suffix is reachable:
 *
 * - line 6, `App\Services\BillingService` — the plain plural, `-s` dropped.
 * - line 12, `App\Policies\UserPolicy` — the `-ies` plural, which no other
 *   rule reaches: drop that branch and `Policies` only ever yields `Policie`.
 * - line 18, `App\Statuses\OrderStatus` — an `-es` plural whose singular drops
 *   both letters. The first implementation of this standard demanded the
 *   non-word `Statuse` here.
 * - line 24, `App\Cases\UseCase` — an `-es` plural whose singular drops only
 *   the `s`. Paired with the line above deliberately: the two spellings are
 *   contradictory, which is why both stems are offered rather than one guessed.
 * - line 30, `App\Analyses\RevenueAnalysis` — an irregular plural, reachable
 *   only through the lookup table.
 * - line 36, `App\Http\Controllers\Api\TokenController` — the match is on a
 *   *grandparent* segment. Check only the immediate parent (`Api`) and this
 *   ordinary Laravel controller is never reported.
 * - line 42, `App\Livewire\Forms\LoginForm` — the folder the superseded
 *   version of this standard exempted. It carries no carve-out now.
 * - line 48, `App\Http\Controllers\Controller` — the name is nothing but the
 *   segment. Require the suffix to follow something and Laravel's own base
 *   controller escapes.
 * - lines 54, 58 and 62 — interface, trait and enum. One keyword per line, so
 *   a declaration keyword dropped from the registration is a failure here
 *   rather than a silent narrowing.
 * - line 69, `App\Notifications\OrderNOTIFICATION` — the suffix in a different
 *   case from the segment.
 * - line 75, `App\WEBHOOKS\StripeWebhook` — the *segment* in a different case
 *   from the suffix. The two normalizations are separate, so each needs its
 *   own shape.
 * - line 81, `App\Services\Service\BillingService` — the one shape where two
 *   ancestors both match, `Service` through its own name and `Services`
 *   through its singular. Contrived as a folder layout, and the only way to
 *   exercise a declaration that could be reported twice; what it pins is
 *   asserted below.
 * - line 87, `App\Movies\Movie` — the `-ies` plural of a word that already
 *   ends in `e`. Let `-ies` answer with its `-y` stem alone and `Movies`
 *   offers only `Movy`, so this ordinary shape goes unreported; every ending
 *   that applies has to contribute a stem, not just the longest one.
 */
it('flags every redundant suffix at its own line', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 6, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 12, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 18, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 24, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 30, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 36, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 42, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 48, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 54, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 58, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 62, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 69, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 75, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 81, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 87, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
    ]);
});

/**
 * The message has to name the segment that matched and the suffix to drop,
 * because those are what tell a developer which of several ancestors the name
 * echoed. Asserting the tuples above cannot see either.
 *
 * The keyword is asserted with them: a declaration reported as the wrong kind
 * would still produce the right line, and the map that supplies the word is
 * the same map that decides what the sniff registers on.
 */
it('names the repeated segment, the suffix, and the kind of declaration', function (): void {
    $messages = violationMessages(analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php'));

    expect($messages[0])->toContain('Class BillingService repeats its own Services namespace segment')
        ->and($messages[0])->toContain('drop the redundant "Service" suffix')
        ->and($messages[4])->toContain('Class RevenueAnalysis repeats its own Analyses namespace segment')
        ->and($messages[4])->toContain('drop the redundant "Analysis" suffix')
        ->and($messages[8])->toContain('Interface RefundablePayment')
        ->and($messages[9])->toContain('Trait RecordsPayment')
        ->and($messages[10])->toContain('Enum CapturedPayment')
        ->and($messages[14])->toContain('Class Movie repeats its own Movies namespace segment')
        ->and($messages[14])->toContain('drop the redundant "Movie" suffix');
});

/**
 * The file-scoped namespace — `namespace App\Services;` with no braces — is the
 * form every Laravel application actually writes, and no other fixture asserts
 * a violation under it: the braced blocks above are what lets one file carry
 * many namespaces, and nameless.php is file-scoped but asserts silence. Without
 * this, a namespace lookup that resolved only the braced form would report
 * nothing on real code and leave the whole suite green.
 *
 * Both directions are pinned in the one file: `BillingService` on line 7 is the
 * violation, and `Billing` on line 11 — the standard's own fix, under the same
 * declared namespace — is the silence.
 */
it('reads a file-scoped namespace, not only a braced block', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'file-scoped-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 1, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
    ])->and(violationMessages($file)[0])
        ->toContain('Class BillingService repeats its own Services namespace segment');
});

/**
 * The all-ancestors rule, read off the message rather than the line: the
 * declaration on line 36 sits in `App\Http\Controllers\Api`, and the segment
 * it repeats is `Controllers` — two levels up, with a non-matching segment in
 * between. A sniff that reported the immediate parent would name `Api` here,
 * and one that stopped at the first non-match would say nothing at all.
 */
it('reports the deepest matching ancestor, not only the immediate parent', function (): void {
    $messages = violationMessages(analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php'));

    expect($messages[5])->toContain('Class TokenController repeats its own Controllers namespace segment');
});

/**
 * One report per declaration, and the deepest ancestor is the one it names.
 * `App\Services\Service\BillingService` (line 81) is the only fixture where
 * two ancestors both hold — `Service` directly, `Services` through its
 * singular — so it is the only one that can tell three behaviours apart: the
 * loop stopping on the first hit rather than reporting every match, the walk
 * starting at the deepest segment, and the message naming the segment that
 * actually matched. Drop the `return` and this line reports twice.
 */
it('reports a declaration once, against its deepest matching ancestor', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->each->toBe(1)
        ->and(violationMessages($file)[13])
        ->toContain('Class BillingService repeats its own Service namespace segment');
});

/**
 * Renaming a type means rewriting every reference to it across the project,
 * which a single-file fixer cannot see, let alone do. Pinned at the severity
 * the standard speaks in as well: errors, and nothing fixable.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * A `class` keyword with no name after it is what PHP_CodeSniffer hands a
 * sniff for a file caught mid-edit. getDeclarationName() answers null, and
 * without the guard str_ends_with() is handed null and the run dies with a
 * TypeError. The namespace is `App\Services`, so this file would be a
 * violation the moment a name arrives — the silence is the guard, not the
 * fixture having nothing to react to.
 */
it('passes over a declaration keyword with no name', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The sniff run over the standard's own source. Every `.php` file this package
 * ships under CleanCode/ has to come back silent, and that is not a
 * coincidence to be re-derived each time the tree grows: every sniff here is
 * named `…Sniff` inside a `Sniffs` namespace, which is precisely the
 * redundancy this rule reports. They are silent because the namespace is
 * headed by `MikeBronner` rather than `App` — so this test is what fails if the
 * application-root gate is ever widened, and it fails on real code rather than
 * on a fixture written to expect it.
 *
 * The token count is asserted per file because the paths come from a glob. A
 * file PHP_CodeSniffer cannot read yields no tokens and therefore no
 * violations, so without it this test would go quietly green the day the tree
 * moves — passing for the one reason it must never pass for. The dataset is
 * asserted non-empty for the same reason one level up.
 */
it('leaves the package own source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/CleanCode/*/*/*.php');

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([REDUNDANT_NAMESPACE_SUFFIX], $path);

        expect($file->numTokens)->toBeGreaterThan(0)
            ->and($file->getErrors())->toBe([])
            ->and($file->getWarnings())->toBe([]);
    }
});

/**
 * The end-to-end run through the installed package, in both directions. The
 * assertions above drive the sniff through PHPCS's API inside this process;
 * this one runs the shipped binary against the shipped standard, which is what
 * a consumer's `vendor/bin/phpcs` does.
 */
it('reports the violation end to end through the installed package', function (): void {
    expect(installedSniffFixtureRun(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php')['status'])->toBe(1)
        ->and(installedSniffFixtureRun(REDUNDANT_NAMESPACE_SUFFIX, 'passing.php')['status'])->toBe(0);
});
