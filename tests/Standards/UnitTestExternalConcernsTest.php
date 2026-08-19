<?php

/**
 * Tests the custom CleanCode.Testing.UnitTestExternalConcerns sniff (Testing:
 * Test Suites, #60, content-vs-directory slice #148). Fixtures live in
 * tests/fixtures/UnitTestExternalConcernsSniff/. The rule is detection-only, so
 * there is no autofixed fixture.
 *
 * The sniff reads two things: the file's path, which decides whether it is
 * looked at at all, and the file's tokens, which decide what is reported. That
 * split is why this directory holds more than the two flat fixtures.
 *
 * passing.php and failing.php have a fixed *location* as well as a fixed name,
 * and that location — tests/fixtures/UnitTestExternalConcernsSniff/ — carries no
 * `Unit` segment, so under the shipped scope the sniff never opens them. They
 * are driven here with unitTestPath pointed at their own directory, which is
 * what makes their content the only variable: passing.php's silence is then
 * about the shapes in it rather than about the sniff having been switched off,
 * which is the failure mode a compliant fixture outside the scope would hide.
 *
 * The shipped scope is exercised by the nested tree instead, and the four files
 * under it are byte-identical to tests/Unit/external-concerns.php on purpose.
 * Only their directory differs, so the silence asserted for the out-of-scope
 * three can come from nothing but the path check.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Testing\UnitTestExternalConcernsSniff;

const UNIT_EXTERNAL = 'CleanCode.Testing.UnitTestExternalConcerns';

const UNIT_EXTERNAL_TRAIT = UNIT_EXTERNAL . '.DatabaseTrait';

const UNIT_EXTERNAL_FAKE = UNIT_EXTERNAL . '.FacadeFake';

const UNIT_EXTERNAL_HTTP = UNIT_EXTERNAL . '.HttpRequest';

/**
 * The scope the two flat fixtures are driven under — their own directory,
 * spelled as the segments that reach it from the test root.
 */
const UNIT_EXTERNAL_FLAT_SCOPE = ['unitTestPath' => 'tests/fixtures'];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNIT_EXTERNAL);
});

/**
 * Every shape in passing.php stays silent while the sniff is looking straight
 * at it, and each one pins a different reason for that silence:
 *
 * - `Support\Refresher as RefreshDatabase` — an alias spelled like a watched
 *   trait. The alias is a different symbol from what is imported, so the name
 *   is closed at the `as` and the alias never read as one. Read it and this
 *   line reports.
 * - `Support\RefreshDatabaseHelper` — a name that merely *starts* with a
 *   watched one. Trailing segments are compared whole.
 * - `use function …\refreshDatabase` and `use const Support\HTTP` — kind
 *   markers, at the head of a statement. A function named like a trait is not
 *   the trait; before the marker was read by content this exact line reported,
 *   because PHP_CodeSniffer tokenizes `function` here as T_STRING rather than
 *   T_FUNCTION.
 * - `…\{function refreshDatabase, const REFRESH_DATABASE}` — the same markers
 *   per-member inside a group import, which is a separate position in the scan.
 * - the trait adaptation block — its body names traits, and none of them is a
 *   new use.
 * - `Http::assertNothingSent()`, `Http::assertSent()` — a watched facade, not
 *   the `fake()` that installs the double. `Cache::fake()` and `Router::fake()`
 *   — `fake()`, but not on a watched facade. `Http::fake` with no `(` —
 *   a constant fetch, which installs nothing.
 * - `$repository->get(…)` / `$repository->getJson(…)` — a request-method name
 *   on a receiver that is not `$this`. `$this->getName()` / `$this->postProcess()`
 *   — `$this`, but not a request method. `$this->get` with no `(` — a property
 *   fetch.
 * - `function () use ($refreshDatabase)` — a closure capture list, which shares
 *   the T_USE keyword with the imports above and carries variables, not names.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'passing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php is the trigger catalogue: every entry of all three configured
 * families appears in it at least once, which is what makes deleting an entry
 * redden this assertion rather than pass unnoticed.
 *
 * The trait side also pins how a `use` statement is read, spelling by spelling:
 *
 * - lines 7-9 — plain imports, one per remaining trait.
 * - line 10 — a *group* import, `…\{LazilyRefreshDatabase}`. PHP_CodeSniffer
 *   mints T_OPEN_USE_GROUP for that brace; read it as an ordinary curly and the
 *   prefix glues onto the member (`Testing{LazilyRefreshDatabase`), the trailing
 *   segment stops matching, and this line silently goes unreported.
 * - line 11 — a group whose first member is unwatched and whose second is
 *   aliased, so it pins both that the scan carries on past a non-match and that
 *   an alias closes only its own member rather than the whole statement.
 * - line 22 — the trait use inside the class body. An import and a trait use
 *   are separate statements and each is reported where it is written; the two
 *   together are what a real file carries.
 * - line 24 — a trait adaptation, reported at the trait named *before* the
 *   brace. Line 25 names the same trait inside the block and is deliberately
 *   not a second report: an adaptation block resolves conflicts between traits
 *   already used, and reading into it would double every one of them.
 *
 * Line 42 is `\Illuminate\Support\Facades\Http::fake()`, the fully qualified
 * spelling, which is what makes the receiver's trailing-segment comparison
 * load-bearing rather than decorative. Line 57 is `$this?->get()`, the nullsafe
 * operator, which is the second member of the object-operator pair.
 */
it('flags every external concern on the failing fixture', function (): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 8, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 9, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 10, 'column' => 36, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 47, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 22, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 24, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 35, 'column' => 12, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 36, 'column' => 14, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 37, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 38, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 39, 'column' => 21, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 40, 'column' => 14, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 41, 'column' => 16, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 42, 'column' => 41, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 47, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 48, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 49, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 50, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 51, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 52, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 53, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 54, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 55, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 56, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 57, 'column' => 17, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

/**
 * The floor under the assertion above: every member of every watched family is
 * actually exercised by failing.php.
 *
 * The tuple assertion alone cannot say this. It pins the lines that *do*
 * report, so an entry with no fixture line of its own is invisible to it —
 * delete that entry from the sniff and the suite stays green, which is exactly
 * how a fail-closed list rots. Adding an entry has the same problem from the
 * other end: it ships uncovered and nothing says so.
 *
 * The families are read off the sniff by reflection rather than transcribed
 * here, so this test measures the shipped lists instead of a copy of them that
 * would have to be kept in step by hand. Each family's members are matched
 * against the concern names the sniff writes into its *messages*, because the
 * message is where it names the specific thing it found — which is also the
 * AC's requirement for it.
 *
 * The names are extracted whole rather than searched for as substrings: `get`
 * occurs inside the message for `getJson`, so a substring test would report
 * coverage for an entry that has no fixture line of its own, which is the
 * failure this test exists to catch.
 */
it('exercises every watched trait, facade and request method', function (string $constant): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);
    $named = [];

    foreach ($file->getWarnings() as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                preg_match(
                    '/: (?:(\w+) stands|(\w+)::fake\(\)|\$this->(\w+)\(\))/',
                    $message['message'],
                    $matches
                );

                $named[] = strtolower(implode('', array_slice($matches, 1)));
            }
        }
    }

    $family = (new ReflectionClassConstant(UnitTestExternalConcernsSniff::class, $constant))->getValue();

    expect($family)->not->toBe([])
        ->and($named)->not->toContain('');

    foreach ($family as $member) {
        expect($named)->toContain($member);
    }
})->with([
    'the database traits' => 'DATABASE_TRAITS',
    'the fakeable facades' => 'FAKEABLE_FACADES',
    'the HTTP-kernel methods' => 'HTTP_KERNEL_METHODS',
]);

/**
 * The shipped scope, reached from a fixture whose real path carries the
 * `tests/Unit` segments. One of each violation family, so no single category
 * can stand in for the others, and the same file is the subject of every
 * out-of-scope assertion below.
 */
it('flags a test under the shipped unit-suite directory', function (): void {
    $file = analyzeFixture(UNIT_EXTERNAL, 'tests/Unit/external-concerns.php');

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 15, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 17, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

/**
 * Each of these is byte-identical to tests/Unit/external-concerns.php, which
 * reports four warnings directly above. Only the directory differs, so silence
 * here is the path check and nothing else — a fixture that merely contained
 * nothing to flag could not tell the two apart.
 *
 * - tests/Feature/ — the plain out-of-scope case. A feature test is *supposed*
 *   to carry every one of these, and the whole rule is that the same tokens
 *   mean different things in different suites.
 * - tests/Unit/tests/Feature/ — two test roots in one path. PHP_CodeSniffer
 *   hands over a fully resolved absolute path, and this package is distributed
 *   for other repositories to require, so the directories above the project are
 *   outside its control. The root closest to the file governs, which resolves
 *   this to the `Feature` suite. Anchor on the first root instead and an
 *   ancestor directory drags a feature test into the unit suite.
 * - tests/UnitOfWork/ — a segment that merely starts with `Unit`. Segments are
 *   compared whole, so a substring match on the path would report this one.
 */
it('leaves an identical file outside the unit suite alone', function (string $fixture): void {
    $file = analyzeFixture(UNIT_EXTERNAL, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'the feature suite' => 'tests/Feature/external-concerns.php',
    'a nested test root below the unit suite' => 'tests/Unit/tests/Feature/nested-root.php',
    'a directory that only starts with the suite name' => 'tests/UnitOfWork/whole-segment.php',
]);

/**
 * Both sides of the scope comparison are folded to lower case, so a project may
 * spell either one the way it likes. The *property* carries the casing here,
 * not the directory: this package is developed on a case-insensitive
 * filesystem, where a `tests/unit/` fixture beside `tests/Unit/` is the same
 * directory and would prove nothing — and would then not resolve at all on the
 * case-sensitive filesystem CI runs on.
 *
 * Set by assignment against the shipped `tests/Unit/` tree, spelled `TESTS/UNIT`.
 * Drop the fold and the root matches no segment, the file is out of scope, and
 * these four warnings disappear.
 */
it('matches the unit-suite directory case-insensitively', function (): void {
    $file = analyzeFixture(
        UNIT_EXTERNAL,
        'tests/Unit/lowercase-suite.php',
        static function (object $sniff): void {
            $sniff->unitTestPath = 'TESTS/UNIT';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 15, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 17, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

/**
 * `$this->get(...)` can be an ordinary userland method on a project's own test
 * case, so a per-line escape hatch is part of this rule rather than a
 * workaround for it. Both spellings PHP_CodeSniffer accepts are exercised, on
 * their own line and trailing the code, and the suppression is left to
 * PHP_CodeSniffer rather than reimplemented in the sniff.
 *
 * Line 19 is the control, and it is what keeps this test honest: an unsuppressed
 * `$this->put()` in the same file and the same method. Without it a sniff that
 * had stopped reporting on this fixture entirely would look exactly like
 * working suppression.
 */
it('skips a line the author has suppressed', function (): void {
    $file = analyzeFixture(UNIT_EXTERNAL, 'tests/Unit/suppressed.php');

    expect(warningTuples($file))->toBe([
        ['line' => 19, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

/**
 * The property is only meaningfully configurable if changing it changes what
 * the sniff reports, and the check has to run in both directions: failing.php
 * is silent under the shipped `tests/Unit/` and speaks under a scope pointed at
 * its own directory. Assert only the second half and a sniff that had stopped
 * looking would pass just as well.
 *
 * Set through Ruleset::setSniffProperty() rather than by assignment, so this
 * pins the path a consuming ruleset's <property> element actually takes.
 */
it('inspects only what the configured unit-test path reaches', function (): void {
    $shipped = analyzeFixture(UNIT_EXTERNAL, 'failing.php');

    expect($shipped->getErrors())->toBe([])
        ->and($shipped->getWarnings())->toBe([]);

    $configured = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect($configured->getWarningCount())->toBe(26);
});

/**
 * An empty property names no directory. The other reading — "an empty prefix
 * matches every path" — would turn one blank <property> element into a warning
 * on every file in a consuming project, so the sniff stays silent instead.
 *
 * What this pins is that *reading*, not the guard that carries it: flip the
 * guard to `return true` and this case goes red, but delete it outright and the
 * scan reaches the same silence by accident, a null root matching no segment.
 * The guard is what makes the decision explicit rather than incidental, and
 * this test is what stops the opposite decision being taken later.
 *
 * Driven by assignment rather than through the ruleset: parsing an empty
 * <property> element hands `null` to a `string`-typed property and aborts the
 * ruleset with a TypeError, so the XML path cannot express this state and the
 * value can only be reached in code.
 */
it('inspects nothing when the configured path is empty', function (): void {
    $file = analyzeFixture(
        UNIT_EXTERNAL,
        'tests/Unit/external-concerns.php',
        static function (object $sniff): void {
            $sniff->unitTestPath = '';
        }
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The message points the reader at the sibling suite, and it is spelled from
 * the configured path rather than hardcoded: a project keeping its suites under
 * `app/Tests/Unit/` is told about `app/Tests/Feature/`, not about a
 * `tests/Feature/` it does not have. The configured casing survives, which is
 * the half a lower-cased path comparison would quietly lose.
 */
it('names the sibling suite from the configured path', function (): void {
    $file = analyzeFixture(
        UNIT_EXTERNAL,
        'tests/Unit/external-concerns.php',
        static function (object $sniff): void {
            $sniff->unitTestPath = 'Tests/Unit';
        }
    );

    $messages = $file->getWarnings()[17][16];

    expect($messages[0]['message'])->toContain('Tests/Feature/');
});

/**
 * Piped input with no --stdin-path gives PHP_CodeSniffer the file name STDIN,
 * so there is no directory for the scope to read — an editor linting a buffer
 * that way would otherwise have to guess which suite the buffer belongs to.
 *
 * The bytes run here are the shipped unit-suite fixture's own, read off disk
 * rather than transcribed, and that same file reports four warnings at its real
 * path in "it flags a test under the shipped unit-suite directory" above. That
 * is what pins the silence to the missing path rather than to the source having
 * nothing the sniff reacts to.
 *
 * What this pins is the behaviour, not the UNKNOWN_PATH guard that states it:
 * delete that guard and the segment scan reaches the same silence on its own,
 * because `STDIN` is a single segment and the file's own name is dropped before
 * the scope is matched, leaving nothing for the root to match. The guard names
 * the decision where a reader of the sniff will look for it; this test is what
 * stops the opposite decision being taken later.
 */
it('says nothing when there is no path to read', function (): void {
    $source = (string) file_get_contents(
        fixturePath(sniffFixtureDirectory(UNIT_EXTERNAL), 'tests/Unit/external-concerns.php')
    );

    $piped = analyzeStdinSource([UNIT_EXTERNAL], $source);

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});
