<?php

/**
 * Tests the custom CleanCode.Testing.NoFirstPartyMocks sniff (Testing:
 * Guidelines, #54, partial enforcement per #146). Fixtures live in
 * tests/fixtures/NoFirstPartyMocksSniff/: the compliant vendor mocks plus every
 * near-miss shape in passing.php, the flagged first-party mocks in failing.php,
 * the property-configurability triple in configured.php, the per-line
 * suppression in suppressed.php, the header-walk boundary in class-body-use.php
 * and the three end-of-file truncations in unterminated-*.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * $firstPartyNamespaces ships EMPTY on the sniff class, so every assertion here
 * that expects a warning depends on rules.xml configuring `App` — which is the
 * same route a consuming project takes. The unconfigured no-op is pinned
 * separately below, from the shipped class default rather than from a value the
 * test invents.
 *
 * Like CleanCode.Testing.NoReflectionAccess, this sniff is meant to run *inside*
 * test paths, so its fixtures are processed where they live — tests/fixtures/
 * matches the shipped tests-directory glob. The reverse direction, silence
 * outside a test path, is pinned twice: once by retuning the property and once
 * against a copy staged outside the repository.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Testing\NoFirstPartyMocksSniff;

const FIRST_PARTY_MOCKS = 'CleanCode.Testing.NoFirstPartyMocks';

const FIRST_PARTY_MOCKS_WARNING = FIRST_PARTY_MOCKS . '.Found';

const FIRST_PARTY_MOCK_LINES = [13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 35];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(FIRST_PARTY_MOCKS);
});

/**
 * The blessed usage and every near-miss shape stay silent. Each group pins one
 * of the sniff's early returns, and a false positive on any of them makes the
 * rule unusable in a real suite:
 *
 * - lines 11-17, mocks of classes the project does not control, reached every
 *   way a class reference can be written: through an import, fully qualified,
 *   as a string literal, as a global class, and through both halves of a group
 *   import (aliased and plain). This is what the standard actually asks for.
 * - lines 21-22, `\Application\Order` and `\Apples` — roots that merely *start*
 *   with the configured `App`. The comparison is segment-wise; a plain string
 *   prefix would swallow both.
 * - lines 25-31, references the file's own tokens cannot resolve: a variable, a
 *   call, two concatenations, a class constant that is not `::class`, an empty
 *   argument list, and an interpolated string. A name that cannot be resolved
 *   is never guessed at. The two concatenations are separate cases, not a
 *   duplicate: the argument-boundary check runs on the `::class` path and on
 *   the string-literal path independently, and line 28's `'App\Models\User'
 *   . $suffix` resolves first-party if the literal path skips it.
 * - lines 35-38, calls that create no mock: two member names outside
 *   $mockCreators (one of which, `mockery`, *contains* a configured name, so
 *   the membership test must not degrade into a substring match), a property
 *   read of a creator name, and a dynamic member name.
 * - lines 42-48, a class *declaring* a member named `mock` — a declaration
 *   carries no `->`, `?->` or `::` before the name, so the sniff never
 *   registers on it. A test-double factory must not flag itself.
 *
 * Two of the sniff's checks are shape guards rather than behavioural branches,
 * and no fixture here or anywhere can discriminate them, so that is said
 * outright instead of being left to imply coverage. Both were verified by
 * mutation — the suite stays green with either deleted:
 *
 * - the member token must be T_STRING. Delete it and a dynamic member name
 *   (line 38) reaches the name comparison as `{` or `$creator`, neither of
 *   which any configured creator can equal.
 * - the token after the member name must open a parenthesis. Delete it and a
 *   property read (line 37) looks for its first argument at whatever follows
 *   the member — and nothing that may legally follow a member name in PHP can
 *   start a class reference, so the result is the same either way.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every first-party mock is flagged, once, at its own line. Grouped by what
 * each line forces.
 *
 * The eight call forms the AC names:
 *
 * - line 13, `$this->createMock()`; line 14, `$this->createPartialMock()`;
 *   line 15, `$this->getMockBuilder()` — PHPUnit.
 * - line 16, `Mockery::mock()`; line 17, `Mockery::spy()` — the static
 *   spelling, which is why `::` is registered alongside `->`.
 * - line 18, `$this->mock()`; line 19, `$this->partialMock()`; line 20,
 *   `$this->spy()` — Laravel's test helpers.
 *
 * The class-reference spellings, each resolved differently:
 *
 * - line 13, `User::class` — resolved through a plain import.
 * - line 14, `\App\Models\User::class` — already fully qualified. PHPCS 3.x
 *   backfills PHP 8's single qualified-name token into T_NS_SEPARATOR +
 *   T_STRING, so reading one token here yields `\`; this line is what forces
 *   the token-run walk.
 * - line 15, `'App\Models\User'` — a string literal, which PHP never resolves
 *   through imports and this sniff therefore reads as already qualified.
 * - line 17, `Pay::class` — an aliased import (`as Pay`).
 * - line 18, `Bill::class` — the aliased half of a group import.
 * - line 19, `namespace\Support\Clock::class` — relative to the declared
 *   namespace, which PHPCS backfills as T_NAMESPACE + T_NS_SEPARATOR.
 * - line 24, `"App\\Models\\User"` — a double-quoted literal, where each
 *   separator is escaped and has to collapse back to one.
 * - line 27, `Support\Clock::class` — no import matches `Support`, so it
 *   resolves inside the current namespace, which is PHP's own fallback.
 *
 * The comparisons:
 *
 * - line 21, `?->mock()` — the nullsafe operator is a separate token and has
 *   to be registered alongside the ordinary one.
 * - line 22, `\APP\Models\User::class` — PHP resolves namespaces
 *   case-insensitively, so the root comparison does too.
 * - line 26, `$this->CREATEMOCK()` — method names are case-insensitive too.
 *
 * The remaining shapes:
 *
 * - line 23, `getMockBuilder(User)` — a bare name with no `::class`, the third
 *   spelling the AC names.
 * - line 25, `mock(User::class, static fn () => null)` — the class reference
 *   is the *first* argument of several, so the argument-boundary check has to
 *   accept a comma as well as a closing parenthesis.
 * - line 28, `Dispatch::class`, and line 29, `Mode::class` — each shadowed by a
 *   later `use function` / `use const` import of the same name. A parser that
 *   read those as class imports would rebind the alias to `Vendor\Sdk\…` and
 *   fall silent on both.
 * - line 35, the same call inside a real test method — the shape the standard
 *   is actually aimed at.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(array_keys(violationSourcesByLine($file->getWarnings())))->toBe(FIRST_PARTY_MOCK_LINES)
        ->and(violationSourcesByLine($file->getWarnings()))
        ->each->toBe([FIRST_PARTY_MOCKS_WARNING]);
});

/**
 * Each warning is reported at the class reference itself, not at the mock
 * creator or the operator, so an editor's inline marker sits under the class
 * that should not be mocked. Columns are asserted for one line per reference
 * shape rather than all eighteen: the plain import (line 13), the fully
 * qualified name (line 14, the leading `\` where the name starts), and the
 * string literal (line 15, the opening quote).
 */
it('reports at the class reference rather than the mock creator', function (): void {
    expect(warningTuples(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')))
        ->toContain(['line' => 13, 'column' => 27, 'source' => FIRST_PARTY_MOCKS_WARNING])
        ->toContain(['line' => 14, 'column' => 37, 'source' => FIRST_PARTY_MOCKS_WARNING])
        ->toContain(['line' => 15, 'column' => 34, 'source' => FIRST_PARTY_MOCKS_WARNING]);
});

/**
 * The message names the *resolved* class and points at the standard it
 * enforces, so a developer reading the report knows both which mock to drop and
 * why. Resolved rather than written is the point of every assertion here: an
 * import (line 13), an alias (line 17) and a relative name (line 19) each say
 * something different in the source than the name the sniff actually matched
 * on, and reporting the written form would hide which class was matched. Line
 * 22 pins that the *source* casing survives into the message even though the
 * comparison folds it.
 *
 * Three of these lines are the *only* thing that can catch their own defect,
 * because getting them wrong still produces a warning on the same line and so
 * slips past the line-and-column assertions above:
 *
 * - lines 15 and 24, the single- and double-quoted string literals. PHP never
 *   resolves a class name written as a string through the file's imports or
 *   namespace, so both must come out as `App\Models\User`. Joining them to the
 *   declared namespace instead yields `App\Tests\Unit\App\Models\User` — still
 *   first-party, still warned, still on the same line, and wrong.
 * - line 27, `Support\Clock::class`, which no import matches. It must fall back
 *   to the current namespace (`App\Tests\Unit\Support\Clock`) rather than being
 *   read as a global name.
 */
it('names the resolved class and the standard in the warning message', function (): void {
    $warnings = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings();

    expect($warnings[13][27][0]['message'])
        ->toContain('App\Models\User')
        ->toContain('mock only interfaces you do not control')
        ->toContain('docs/standards/testing-guidelines.md')
        ->and($warnings[17][21][0]['message'])->toContain('App\Services\Payments')
        ->and($warnings[19][32][0]['message'])->toContain('App\Tests\Unit\Support\Clock')
        ->and($warnings[22][36][0]['message'])->toContain('APP\Models\User')
        ->and($warnings[15][34][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[24][26][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[27][39][0]['message'])->toContain('Mocking App\Tests\Unit\Support\Clock,');
});

/**
 * The shipped class default is an empty namespace list, which makes the sniff a
 * no-op: nothing in a file says which roots a project owns, so an unconfigured
 * sniff stays silent rather than guessing. Asserted from the class itself
 * rather than from a value the test hands over, because the default is the
 * contract — and then exercised end-to-end, since a default that is only
 * *declared* empty proves nothing about what the sniff does with it.
 *
 * process()'s own `$this->firstPartyNamespaces === []` early return is not what
 * this test pins, and no fixture can pin it: isFirstParty() returns false for
 * an empty list anyway, so deleting the early return changes no result
 * (verified by mutation — the suite stays green without it). It is kept as a
 * short circuit, because it is what spares an unconfigured consumer the
 * per-token path-glob matching in isTestFile(), and stated plainly here rather
 * than left to imply coverage.
 */
it('is a no-op until its first-party namespaces are configured', function (): void {
    expect((new NoFirstPartyMocksSniff())->firstPartyNamespaces)->toBe([]);

    $unconfigured = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = [];
        }
    );

    expect($unconfigured->getWarnings())->toBe([])
        ->and($unconfigured->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

/**
 * Both name lists are public sniff properties. One fixture pins both
 * directions for each: under the `App` root rules.xml configures, line 10
 * warns and line 11 does not; replacing the root with `Vendor` swaps them, and
 * replacing $mockCreators with `double` moves the single warning to line 15. A
 * property that was ignored would leave every run identical and fail the second
 * and third assertions.
 */
it('exposes configurable namespace and mock-creator lists', function (): void {
    expect(array_keys(analyzeFixture(FIRST_PARTY_MOCKS, 'configured.php')->getWarnings()))
        ->toBe([10]);

    $retunedNamespaces = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect(array_keys($retunedNamespaces->getWarnings()))->toBe([11]);

    $retunedCreators = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->mockCreators = ['double'];
        }
    );

    expect(array_keys($retunedCreators->getWarnings()))->toBe([15]);
});

/**
 * The route a consuming project actually takes: a ruleset that references
 * rules.xml and then replaces the namespace list with `<element>` entries. This
 * is a different parse path from the $configure callback above — PHPCS builds
 * the array itself from the XML — and it is the one the sniff's doc advertises,
 * so it is asserted rather than assumed. Both directions again: `Vendor`
 * silences line 10 and reports line 11, the exact inverse of the shipped
 * configuration.
 */
it('accepts its namespace list from a consuming ruleset in XML', function (): void {
    $configured = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'configured.php',
        ['firstPartyNamespaces' => ['Vendor']]
    );

    expect(array_keys($configured->getWarnings()))->toBe([11]);
});

/**
 * The test-file pattern is a public sniff property too, and it is what gates
 * the whole rule: retuning it to a glob the fixture's path cannot match must
 * silence a file that otherwise reports eighteen times.
 *
 * Both halves are asserted together. The retuned run alone would pass just as
 * well against a sniff that never fires at all, which is precisely the failure
 * mode a scoped rule makes easy to ship unnoticed.
 */
it('exposes a configurable test-file pattern that gates the whole rule', function (): void {
    $retuned = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->testFilePatterns = ['*/production/*'];
        }
    );

    expect($retuned->getWarnings())->toBe([])
        ->and($retuned->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

/**
 * The same thing from the other side, with the shipped defaults untouched: the
 * identical bytes, at a path that is genuinely not a test path, report nothing.
 * The retuned-property test above proves the property is consulted; this one
 * proves the shipped patterns actually distinguish a real non-test location,
 * which a hand-picked glob cannot.
 *
 * The staged copy keeps the fixture's name and lands under the system temp
 * directory, so it matches neither shipped pattern. The staged copies are
 * removed by the afterEach() hook in tests/Pest.php.
 */
it('never inspects a file outside a test path', function (): void {
    $staged = analyzeWithSniffs(
        [FIRST_PARTY_MOCKS],
        stageFixtureOutsideTests(fixturePath('NoFirstPartyMocksSniff', 'failing.php'))
    );

    expect($staged->getWarnings())->toBe([])
        ->and($staged->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

/**
 * The import walk reads a file's header and stops at the first token that
 * cannot belong to one, so a trait's `use` inside a class body never enters the
 * import map. The fixture makes that discriminating rather than decorative:
 * `use \Vendor\Sdk\Client;` sits in a class body, and a walk that kept going
 * would bind the alias `Client` to a vendor class and fall silent on the mock
 * that follows. Stopping at the class keeps `Client` unimported, so it resolves
 * inside App\Tests\Unit and reports.
 *
 * The stop is also what bounds the walk's cost: it reads a few dozen header
 * tokens per mock call rather than the whole file, so a test file with many
 * mocks does not turn the scan quadratic.
 */
it('reads imports from the file header only', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'class-body-use.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            14 => [FIRST_PARTY_MOCKS_WARNING],
        ]);
});

/**
 * The gray areas the standard's doc names — a first-party facade or contract
 * wrapping a genuinely external service — take the ordinary PHPCS per-line
 * suppression rather than a sniff-specific escape hatch. Lines 11 and 13 carry
 * `phpcs:ignore` (on the preceding line and trailing the statement
 * respectively, the two placements PHPCS supports) and report nothing; lines 19
 * and 20 carry the same constructs unsuppressed and still report, so a sniff
 * that had fallen silent on the whole file would fail here rather than pass.
 */
it('honours per-line phpcs:ignore suppression', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'suppressed.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            19 => [FIRST_PARTY_MOCKS_WARNING],
            20 => [FIRST_PARTY_MOCKS_WARNING],
        ]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so a statement can end at `->`, at
 * an open parenthesis, or at a `::` with nothing after it at all. Only at
 * end-of-file is there genuinely no following token, which is the only way to
 * reach any of the three `false` guards — mid-file the walk finds a `;` and
 * falls out on a type check instead. The sniff has to pass over all three
 * rather than fall over or invent a diagnostic.
 *
 * Each fixture still reports its two intact mocks (lines 7 and 8), so a sniff
 * that bailed on the whole file would fail rather than pass.
 */
it('handles a truncated statement without falling over', function (string $fixture): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and(array_keys($file->getWarnings()))->toBe([7, 8]);
})->with([
    ['unterminated-member.php'],
    ['unterminated-call.php'],
    ['unterminated-class-constant.php'],
]);

/**
 * Pins the detection-only decision: replacing a mock of a class you own with
 * the real collaborator is a redesign of the test, so no violation is
 * auto-fixable. Warnings rather than errors, because Testing: Guidelines is
 * advisory and both documented limits — name-based creator matching and the
 * facade/contract gray area — produce known false positives, which must not
 * fail a consumer's build.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php');

    expect($file->getWarningCount())->toBe(count(FIRST_PARTY_MOCK_LINES))
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
