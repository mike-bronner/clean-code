<?php

/**
 * Tests the custom CleanCode.Testing.NoFirstPartyMocks sniff (Testing:
 * Guidelines, #54, partial enforcement per #146). Fixtures live in
 * tests/fixtures/NoFirstPartyMocksSniff/: the compliant vendor mocks plus every
 * near-miss shape in passing.php, the flagged first-party mocks in failing.php,
 * the property-configurability triple in configured.php, the per-line
 * suppression in suppressed.php, the header-walk boundary in class-body-use.php,
 * the string-literal escaping in escaped-literals.php, the group-import
 * emptiness guard in group-import-trailing-comma.php, the mixed function and
 * const clauses in group-import-mixed-keywords.php, the multi-segment and
 * leading-separator imports in import-resolution.php, the scope keywords in
 * scope-keywords.php and the three end-of-file truncations in
 * unterminated-*.php. The rule is detection-only, so there is no autofixed
 * fixture.
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
 * A doubled separator inside a string literal is an escape in *both* quote
 * styles: PHP's single-quote lexer collapses `\\` to one backslash exactly as
 * its double-quote lexer does. So `'App\\Models\\User'` (line 10),
 * `"App\\Models\\User"` (line 11) and `'App\Models\User'` (line 15) all name
 * the same class, and reading the single-quoted form verbatim leaves the
 * resolved name as `App\\Models\\User`.
 *
 * Both configurations are asserted, because each catches a different half of
 * that defect and neither catches the other:
 *
 * - under the `App` root rules.xml ships, the doubled name still matches by
 *   plain prefix luck — `app\` *is* a prefix of `app\\` — so line 10 reports
 *   either way and only the message shows the doubled separators baked into
 *   the resolved name.
 * - under a multi-segment `App\Models` root, which is the shape rules.xml's own
 *   example consumer configuration offers, the luck runs out: `app\models\` is
 *   not a prefix of `app\\models\\`, so line 10 falls silent altogether — a
 *   first-party mock the sniff simply misses. Line 19 sits under `App` but
 *   outside `App\Models` and must stay silent, which is what proves the
 *   narrower root is genuinely in force rather than the shipped one.
 */
it('collapses an escaped separator in either quote style', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'escaped-literals.php');
    $messages = violationMessagesByLine($shipped->getWarnings());

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([10, 11, 15, 19])
        ->and($messages[10][0])->toContain('Mocking App\Models\User,')
        ->and($messages[11][0])->toContain('Mocking App\Models\User,')
        ->and($messages[15][0])->toContain('Mocking App\Models\User,')
        ->and($messages[19][0])->toContain('Mocking App\Services\Payments,');

    $narrowed = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'escaped-literals.php',
        ['firstPartyNamespaces' => ['App\Models']]
    );

    expect($narrowed->getErrors())->toBe([])
        ->and(array_keys($narrowed->getWarnings()))->toBe([10, 11, 15]);
});

/**
 * A trailing comma inside a group import is legal from PHP 8.0 and leaves an
 * empty clause behind. The clause has to be dropped *before* the group prefix
 * is applied to it: prefixed first, the empty clause becomes `Vendor\Sdk\` —
 * never empty, so no emptiness guard downstream can catch it — and is imported
 * as the phantom alias `sdk => Vendor\Sdk`, an import the file never wrote.
 *
 * Both directions are pinned on one fixture, because the phantom alias hides in
 * a different way under each root:
 *
 * - under the `App` root rules.xml ships, line 22's `Sdk` must resolve inside
 *   the declared namespace as `App\Tests\Unit\Sdk` and report. The phantom
 *   alias would send it to `Vendor\Sdk` and swallow the warning.
 * - under a `Vendor` root it is the inverse, and the false *positive* the AC
 *   forbids outright: an unresolvable-through-imports name must not be flagged,
 *   yet the phantom alias makes `Sdk` look first-party.
 *
 * Line 27 is the second group's own copy of the defect, and it needs the
 * message rather than the line: `Models` resolves to `App\Tests\Unit\Models`,
 * and the phantom `models => App\Models` sends it to `App\Models` instead —
 * first-party under `App` either way, same line, different class.
 *
 * Lines 13 to 16 keep the fixture honest in the other direction: both halves of
 * both groups still import, so a parser that dropped the last clause of a
 * trailing-comma group (rather than inventing one) fails here too.
 */
it('ignores the empty clause a trailing comma leaves in a group import', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'group-import-trailing-comma.php');

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([13, 14, 22, 27])
        ->and(violationMessagesByLine($shipped->getWarnings())[27][0])
        ->toContain('Mocking App\Tests\Unit\Models,');

    $vendor = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'group-import-trailing-comma.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([15, 16]);
});

/**
 * A `function` or `const` keyword in a `use` statement is read where PHP reads
 * it — on the statement, binding every clause, and on a group's own clause,
 * binding that clause alone — and in both places before the group's namespace
 * prefix is applied. Prefixed first, the keyword lands mid-string, an anchored
 * test can no longer see it, and the clause is imported as a class under
 * whatever alias it carries.
 *
 * Both roots are asserted, because the misparse hides in a different direction
 * under each and the sniff must be wrong in neither:
 *
 * - under the shipped `App` root, lines 24-25 and 29-32 name things no `use`
 *   statement imported as a class, so all six resolve inside the declared
 *   namespace and report. Read as class imports they bind to `Vendor\Sdk\…`
 *   and fall silent — a first-party mock the sniff simply misses.
 * - under a `Vendor` root it is the inverse, and the false *positive* the AC
 *   forbids outright: `Vendor\Sdk\function build` is not a class any more than
 *   `Baz` is first-party, yet the misparse makes both look that way. Only line
 *   19's `Helper`, the group's real class clause, may report there.
 *
 * Line 19 carries the other half in both runs: dropping a whole group because
 * one of its clauses names a function would take the classes beside it down
 * too, and that shows up as silence here under `Vendor`.
 */
it('reads a function or const keyword before it prefixes a group clause', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'group-import-mixed-keywords.php');

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([24, 25, 29, 30, 31, 32]);

    $vendor = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'group-import-mixed-keywords.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([19]);
});

/**
 * An import binds its alias to a whole namespace, so a reference written as
 * `Models\Comment` keeps every segment past the alias: `App\Models\Comment`,
 * not `App\Models`. Every other import-resolved reference in these fixtures is
 * a bare one-segment name, which a resolution that kept only the alias would
 * satisfy just as well.
 *
 * The truncated name sits under the same `App` root as the full one, so the
 * shipped configuration cannot tell them apart on the line alone. Three runs
 * pin it, each catching what the others cannot:
 *
 * - under `App`, the *message* is the discriminator — `App\Models\Comment` and
 *   `App\Models\Comment\Draft` against the `App\Models` a truncation yields,
 *   same lines either way. Line 19 comes with them: `use \App\Enums\Status`
 *   imports `App\Enums\Status`, and keeping the leading separator binds the
 *   alias to `\App\Enums\Status`, which no root can match.
 * - under `App\Models\Comment`, the line is the discriminator: the full names
 *   match that root and `App\Models` does not, so a truncation empties the
 *   report. Line 19 must fall out here, which is what proves the narrower root
 *   is genuinely in force rather than the shipped one.
 * - under `Vendor\Sdk\Client`, the same for a vendor import, in the direction
 *   the App runs cannot reach: line 24 resolves to `Vendor\Sdk\Client` and
 *   reports, and to the silent `Vendor\Sdk` if the trailing segment is dropped.
 */
it('keeps every segment written past an import alias', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'import-resolution.php');
    $messages = violationMessagesByLine($shipped->getWarnings());

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([17, 18, 19])
        ->and($messages[17][0])->toContain('Mocking App\Models\Comment,')
        ->and($messages[18][0])->toContain('Mocking App\Models\Comment\Draft,')
        ->and($messages[19][0])->toContain('Mocking App\Enums\Status,');

    $narrowed = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'import-resolution.php',
        ['firstPartyNamespaces' => ['App\Models\Comment']]
    );

    expect($narrowed->getErrors())->toBe([])
        ->and(array_keys($narrowed->getWarnings()))->toBe([17, 18]);

    $vendor = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'import-resolution.php',
        ['firstPartyNamespaces' => ['Vendor\Sdk\Client']]
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([24]);
});

/**
 * The namespace list is a list: a class under *any* configured root is
 * first-party, not just one under the first. That is the sniff's own documented
 * extensibility path — rules.xml teaches a two-root consumer configuration — so
 * it is asserted rather than left to the single-root tests above, all of which
 * a comparison that only ever read `firstPartyNamespaces[0]` would pass.
 *
 * `Vendor` is listed first and `App` second, so configured.php's line 10
 * (`App\Models\User`) can only report through the *second* entry, and line 11
 * (`Vendor\Sdk\Client`) only through the first. Both are asserted together: the
 * pair is what forces a real union rather than either entry winning alone.
 *
 * Configured through XML rather than the callback, because two `<element>`
 * entries under one property is the exact spelling rules.xml documents and the
 * one a consuming project writes.
 */
it('treats a class under any configured namespace root as first-party', function (): void {
    $configured = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'configured.php',
        ['firstPartyNamespaces' => ['Vendor', 'App']]
    );

    expect($configured->getErrors())->toBe([])
        ->and(array_keys($configured->getWarnings()))->toBe([10, 11]);
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
 * `self::class`, `static::class` and `parent::class` are the same `::class`
 * constant the AC names, naming the enclosing scope instead of writing a name
 * out — and `$this->createPartialMock(static::class, [...])` is the idiomatic
 * way to partial-mock the class a test file is about, so leaving them
 * unresolved would miss the commonest first-party partial mock there is.
 * PHP_CodeSniffer gives each keyword its own token (T_SELF, T_STATIC, T_PARENT),
 * none of them among the name tokens the ordinary run walk reads.
 *
 * Flagged, lines 69 to 72: `self::class` and `static::class` both resolve to
 * the class the call is written in, `parent::class` to the `extends` clause,
 * and `SELF::class` proves the keywords are matched as tokens rather than by
 * spelling. Registering only one of the three keywords silences the other two,
 * so the four lines pin the list as well as the resolution. Line 71 needs its
 * message rather than its line — `User` resolves through the file's import to
 * `App\Models\User`, and joining the extends clause to the current namespace
 * instead yields `App\Tests\Unit\User`, still first-party, still warned, same
 * line, wrong class.
 *
 * Silent, each verified by mutation — the mutation named against each line is
 * the one that makes that line report:
 *
 * - line 9, outside every class-like scope: there is no enclosing class to
 *   name. Falling back to the file's first class flags it.
 * - line 17, inside a trait: `self` names whichever class uses the trait.
 *   Resolving any class-like scope rather than a named one flags it as
 *   `App\Tests\Unit\Doubles`.
 * - line 30, inside an anonymous class nested in a method: `self` names the
 *   anonymous class. Dropping T_ANON_CLASS from the class-like scopes lets the
 *   walk reach past it to AnonymousHostTest and flag the line.
 * - line 40, `parent::class` in a class extending a vendor one — resolved,
 *   through the import, to a class that is correctly not first-party. Reading
 *   `parent` as the enclosing class instead flags it.
 * - line 50, `parent::class` in a class whose `extends` has no name after it,
 *   as a file caught mid-edit leaves it. Dropping the guard for that takes the
 *   whole run down with a TypeError rather than misreporting.
 * - line 61, `parent::class` in a class with no `extends` clause at all. Both
 *   searches parentName() makes are bounded by that class's own brace, and the
 *   two bounds express one invariant — either alone still holds the line, and
 *   dropping *both* finds the `extends User` of the class declared next and
 *   flags lines 50 and 61 together.
 * - line 83, `self::class . 'Proxy'` — a concatenation, so the reference is not
 *   the whole argument.
 * - line 88, a bare `self` with no `::class` at all. The written-name path
 *   accepts a bare `User` (failing.php line 23) because a name is a name
 *   whatever follows it; a keyword names a class only through the constant.
 *
 * Three shapes here are covered rather than pinned, and that is said outright
 * instead of being left to imply coverage, as the compliant-fixture test above
 * does for its own two:
 *
 * - line 78, `self::DRIVER`, is rejected by the same `::class` check the
 *   written-name path uses, which passing.php line 29 already pins. It is kept
 *   as the keyword-path instance of that shape, not as new coverage.
 * - lines 79-80, a `static` closure, is guarded twice over — the keyword
 *   carries no `::class`, and `function` does not end the argument either — so
 *   neither guard alone reports it.
 * - scopeClassName()'s rejection of a declaration PHPCS gives no name for has
 *   no discriminating fixture at all: the only unnamed class there is
 *   tokenizes as T_ANON_CLASS, which enclosingClass() has already stopped on.
 *   Deleting it changes no result here (verified by mutation). It is kept so
 *   the method fails closed on a name it does not have rather than joining a
 *   null.
 */
it('resolves self, static and parent to the class the call sits in', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'scope-keywords.php');
    $warnings = $file->getWarnings();

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            69 => [FIRST_PARTY_MOCKS_WARNING],
            70 => [FIRST_PARTY_MOCKS_WARNING],
            71 => [FIRST_PARTY_MOCKS_WARNING],
            72 => [FIRST_PARTY_MOCKS_WARNING],
        ])
        ->and($warnings[69][37][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,')
        ->and($warnings[70][42][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,')
        ->and($warnings[71][36][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[72][36][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,');
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
