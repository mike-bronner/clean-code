<?php

/**
 * Tests the custom CleanCode.Testing.RequireTestFile sniff (Testing:
 * Development Process (TDD), #57/#128). Fixtures live in
 * tests/fixtures/RequireTestFileSniff/. The rule is detection-only — the fix is
 * writing the missing test — so there is no autofixed fixture.
 *
 * The sniff answers a question about the *filesystem*, not about one file's
 * tokens: does a companion test exist at the path this file's location implies?
 * That is why this fixture directory holds a small real project rather than the
 * usual flat pair. src/, app/, standalone/ and a second checkout under srv/ are
 * ordinary committed fixtures, and each companion under tests/ exists purely so
 * the lookup has something to find. None of them is collected as a test:
 * phpunit.xml.dist lists five suite directories and tests/fixtures/ is not among
 * them, so a file named CoveredTest.php sitting there is read by the sniff and
 * by nothing else.
 *
 * The flat passing.php and failing.php keep the contract floor, but their fixed
 * names fix their location too, and that location resolves no test tree — so
 * they are driven with $sourceDirectories pointed at the fixture directory
 * itself, which makes it a source root and tests/fixtures/tests/ (which does not
 * exist) the only place a companion could be. Everything in passing.php is
 * therefore silent because it is *exempt*, never because a test was found, and
 * everything in failing.php reports.
 *
 * The sniff is left out of tests/Contract/SniffContractTest.php's datasets for
 * the same reason CleanCode.Files.NoProceduralCode is: the sweep runs each
 * fixture where it lives and configures nothing, so failing.php would report
 * nothing there whatever the sniff did.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 */

declare(strict_types=1);

const REQUIRE_TEST_FILE = 'CleanCode.Testing.RequireTestFile';

const REQUIRE_TEST_FILE_MISSING = REQUIRE_TEST_FILE . '.Missing';

/**
 * Makes tests/fixtures/RequireTestFileSniff/ itself a source root, which is the
 * only way the flat contract fixtures reach the rule at all. The project root
 * is then tests/fixtures/, which has no tests/ directory beside it.
 */
const FLAT_FIXTURE_SOURCE_ROOT = ['RequireTestFileSniff'];

/**
 * The one concrete class a staged project holds, declared on line 5. Named for
 * the file it is written to in every case but one, where the point is that the
 * companion is resolved from the *file* name.
 */
const STAGED_CLASS = "<?php\n\ndeclare(strict_types=1);\n\nclass Widget\n{\n}\n";

/**
 * A staged companion test. The sniff reads whether this file exists and nothing
 * else about it, so its contents only have to be a file.
 */
const STAGED_COMPANION = "<?php\n\ndeclare(strict_types=1);\n";

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_TEST_FILE);
});

/**
 * The contract floor's compliant half. Every declaration in it is exempt on its
 * own terms, and each pins a different reason:
 *
 * - line 17, `interface Readable` — T_INTERFACE, a token the sniff never
 *   registers.
 * - line 22, `trait Countable` — T_TRAIT, likewise.
 * - line 30, `enum Level` — T_ENUM, likewise.
 * - line 36, `abstract class AbstractBase` — T_CLASS, so this is the one
 *   exemption the sniff has to read off the declaration itself.
 * - line 41, `readonly abstract class ReadonlyAbstractBase` — the same
 *   exemption with the modifiers reversed. PHP accepts either order, so reading
 *   only the token immediately before `class` would flag this one.
 * - line 46, the anonymous class returned from `anonymous()` — T_ANON_CLASS. It
 *   has no name for a companion to be named after.
 *
 * The configuration is what gives the assertion teeth: without it the fixture's
 * own path carries no source root, and the sniff is silent on every line for a
 * reason that has nothing to do with the exemptions above.
 */
it('leaves every exempt declaration alone', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'passing.php', function ($sniff): void {
        $sniff->sourceDirectories = FLAT_FIXTURE_SOURCE_ROOT;
    });

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The contract floor's violating half. Three concrete classes, no companion
 * resolvable from this directory:
 *
 * - line 13, `class PlainService` — the plain declaration, reported at column 1.
 * - line 21, `final class FinalService` — column 7, past the modifier.
 * - line 29, `readonly class ReadonlyService` — column 10, likewise.
 *
 * The two modifiers are here because only `abstract` exempts a class: a check
 * widened to "carries a modifier" would swallow both of these silently.
 */
it('warns on every concrete class with no companion test', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'failing.php', function ($sniff): void {
        $sniff->sourceDirectories = FLAT_FIXTURE_SOURCE_ROOT;
    });

    expect(warningTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
        ['line' => 21, 'column' => 7, 'source' => REQUIRE_TEST_FILE_MISSING],
        ['line' => 29, 'column' => 10, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
});

/**
 * Silence with the shipped defaults and no configuration, one fixture per
 * reason. Each of these either resolves a real companion under the mini
 * project's tests/ directory, or is exempt on the same terms passing.php pins —
 * but here at a path the sniff genuinely scopes itself into:
 *
 * - src/Covered.php — a class directly on the source root, so the
 *   source-relative directory is empty and the expected path must not pick up a
 *   doubled separator from it.
 * - src/Nested/Deep.php — a class below the source root, mirrored at
 *   tests/Nested/DeepTest.php.
 * - src/Renamed.php — the companion is named for the *file*, not for the
 *   `DifferentName` class the file declares.
 * - src/Registry.php — its own companion exists, so the only declaration left
 *   in the file is an anonymous class the sniff must not report.
 * - the five exempt declaration shapes, each with no companion anywhere, so
 *   nothing but the exemption keeps them quiet.
 */
it('says nothing about a file whose companion exists or that is exempt', function (string $fixture): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a companion beside the source root' => 'src/Covered.php',
    'a mirrored companion below it' => 'src/Nested/Deep.php',
    'a companion named for the file, not the class' => 'src/Renamed.php',
    'an anonymous class in a covered file' => 'src/Registry.php',
    'an interface' => 'src/Contract.php',
    'a trait' => 'src/Helper.php',
    'an enum' => 'src/Status.php',
    'an abstract class' => 'src/BaseModel.php',
    'a readonly abstract class' => 'src/ReadonlyBase.php',
]);

/**
 * The violations, with the shipped defaults and no configuration:
 *
 * - src/Untested.php — nothing named for it anywhere.
 * - src/Nested/Orphan.php — a same-named test exists, but at tests/, not at
 *   tests/Nested/. The source-relative directory is part of the expected path;
 *   drop it and this stray file satisfies the lookup.
 * - src/Split.php — its test is at tests/Unit/, one level below the shipped
 *   test root. `*` in a glob pattern does not cross a separator, so the default
 *   mirror mapping does not reach it. The other half of this pair — the same
 *   file falling silent under a `tests/*` root — is asserted below.
 * - src/Migrations/CreateUsersTable.php — framework scaffolding is a violation
 *   until a consuming ruleset opts it out, because $excludePatterns ships empty.
 * - app/Legacy.php — the second shipped source-directory name.
 * - standalone/src/Lonely.php — a project with no tests/ directory at all, so
 *   the expected pattern names a directory that is not there.
 */
it('warns on a concrete class whose companion is absent', function (string $fixture, int $line): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, $fixture);

    expect(warningTuples($file))->toBe([
        ['line' => $line, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
})->with([
    'no test anywhere' => ['src/Untested.php', 10],
    'a same-named test at the wrong level' => ['src/Nested/Orphan.php', 14],
    'a test below the configured test root' => ['src/Split.php', 14],
    'framework scaffolding, not yet excluded' => ['src/Migrations/CreateUsersTable.php', 13],
    'a class under app/' => ['app/Legacy.php', 12],
    'a project with no test directory at all' => ['standalone/src/Lonely.php', 14],
]);

/**
 * PHP_CodeSniffer resolves the file name to an absolute path, so every
 * directory above the project is part of what the sniff reads — and this
 * package is distributed for other repositories to require, so that location is
 * not under its control.
 *
 * srv/app/nested-project/src/Widget.php puts a second source-directory name,
 * `app`, above the real project. The source root is the one closest to the
 * file, which makes nested-project/ the project root and finds the companion at
 * nested-project/tests/WidgetTest.php. Anchor on the first source segment
 * instead and the project root becomes srv/, the lookup goes to
 * srv/tests/WidgetTest.php, and this compliant class reports.
 */
it('anchors the project root on the source directory closest to the file', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'srv/app/nested-project/src/Widget.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The expected path is a glob pattern, and every part of it the sniff reads off
 * the filesystem — the directories above the source root, the file's directory
 * relative to it, and the file's own name — is a literal that has to survive
 * being put into one. `*`, `?` and `[...]` are all legal in a directory name on
 * the platforms this package runs on, and none of them is constrained by
 * anything: a source root's parent is wherever the consuming project happens to
 * be checked out.
 *
 * These two tests are staged rather than committed. A directory named `Od*d`
 * cannot exist in a Windows checkout, so putting one under tests/fixtures/ would
 * break `git clone` there instead of testing anything.
 *
 * The silent half — the companion exists at the literal path, and the sniff has
 * to find it. Each case names what an unquoted pattern would look for instead:
 *
 * - `src/Foo[Bar]/Widget.php` — read as a pattern, `[Bar]` is a character class
 *   matching one of B, a or r, so the lookup goes to `tests/FooB/` and this
 *   compliant class is reported.
 * - `proj[1]/src/Widget.php` — the same, in the segments above the source root,
 *   which the sniff folds into the project root.
 * - `src/Odd[Name].php` — the same again, in the name the companion is named
 *   after.
 */
it('finds a companion under a name holding a glob metacharacter', function (array $files): void {
    $file = analyzeWithSniffs([REQUIRE_TEST_FILE], stageProjectOutsideTests($files));

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a bracket group in the source-relative directory' => [[
        'src/Foo[Bar]/Widget.php' => STAGED_CLASS,
        'tests/Foo[Bar]/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group above the source root' => [[
        'proj[1]/src/Widget.php' => STAGED_CLASS,
        'proj[1]/tests/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group in the file name' => [[
        'src/Odd[Name].php' => STAGED_CLASS,
        'tests/Odd[Name]Test.php' => STAGED_COMPANION,
    ]],
]);

/**
 * The other half, and the one the silent half above cannot cover on its own: a
 * metacharacter left unescaped does not only miss a file that is there, it also
 * *matches* one that is not the companion. Each of these stages a test file at
 * the path the unescaped pattern would find and nothing at the literal one, so
 * the warning is what proves the pattern is being matched literally:
 *
 * - `src/Od*d/` against `tests/Odad/` — `*` spans the different character.
 * - `src/Od?d/` against `tests/Odad/` — `?` spans it too, one character wide.
 * - `src/Foo[Bar]/` against `tests/FooB/` — the character class matches its own
 *   first alternative.
 */
it('does not let a glob metacharacter match a directory that is not the companion', function (array $files): void {
    $file = analyzeWithSniffs([REQUIRE_TEST_FILE], stageProjectOutsideTests($files));

    expect(warningTuples($file))->toBe([
        ['line' => 5, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
})->with([
    'an asterisk spanning a different directory name' => [[
        'src/Od*d/Widget.php' => STAGED_CLASS,
        'tests/Odad/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a question mark spanning a different character' => [[
        'src/Od?d/Widget.php' => STAGED_CLASS,
        'tests/Odad/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group matching one of its own characters' => [[
        'src/Foo[Bar]/Widget.php' => STAGED_CLASS,
        'tests/FooB/WidgetTest.php' => STAGED_COMPANION,
    ]],
]);

/**
 * $sourceDirectories is what scopes the sniff, in place of a ruleset
 * <include-pattern>: a project keeping its classes elsewhere retunes the
 * property rather than overriding this ruleset. Narrowing it to `src` alone
 * leaves app/Legacy.php with no source root on its path, so there is nothing to
 * resolve a companion against and the sniff says nothing — the same file that
 * reports with the shipped defaults above.
 */
it('scopes itself out of a directory not named in the source list', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'app/Legacy.php', function ($sniff): void {
        $sniff->sourceDirectories = ['src'];
    });

    expect($file->getWarnings())->toBe([]);
});

/**
 * A suite split into Unit/ and Feature/ is not a mirror of the source tree, and
 * a wildcard in $testDirectory is what keeps it to a single lookup: `tests/*`
 * resolves the expected path one level deeper, which tests/Unit/SplitTest.php
 * matches. The same fixture reports under the shipped `tests` root, asserted
 * above, so the silence here is pinned to the configuration rather than to the
 * file having nothing to say.
 */
it('finds a companion in a split suite through a wildcard test root', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Split.php', function ($sniff): void {
        $sniff->testDirectory = 'tests/*';
    });

    expect($file->getWarnings())->toBe([]);
});

/**
 * $testPathTemplate is the other half of the mapping, and dropping `{path}`
 * from it is how a project says "a test named for this file, directly under the
 * test root". src/Nested/Orphan.php is the fixture that proves the template is
 * read rather than assumed: tests/OrphanTest.php exists at the wrong level for
 * the shipped template and at the right one for this.
 */
it('resolves the companion through the configured path template', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Nested/Orphan.php', function ($sniff): void {
        $sniff->testPathTemplate = '{name}Test.php';
    });

    expect($file->getWarnings())->toBe([]);
});

/**
 * The exclude list, set the way a consuming ruleset sets it — as a
 * <property type="array"> element, through Ruleset::setSniffProperty(), rather
 * than by assigning to the property directly. The two paths are not
 * interchangeable, and only the XML one is what a consumer actually exercises.
 *
 * Both halves are asserted from the same fixture, because a pattern that
 * matched everything would look identical to a working exclude list from the
 * first half alone: a glob naming Migrations silences it, one naming Providers
 * does not.
 */
it('honours an exclude pattern configured from a ruleset', function (array $patterns, array $expected): void {
    $file = analyzeFixtureWithRulesetProperties(
        REQUIRE_TEST_FILE,
        'src/Migrations/CreateUsersTable.php',
        ['excludePatterns' => $patterns]
    );

    expect(warningTuples($file))->toBe($expected);
})->with([
    'a matching pattern' => [['*/Migrations/*'], []],
    'a pattern that matches nothing here' => [
        ['*/Providers/*'],
        [['line' => 13, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING]],
    ],
]);

/**
 * Piped input with no --stdin-path gives PHP_CodeSniffer the file name STDIN,
 * which resolves no location at all — an editor linting a buffer that way would
 * otherwise report every class in the project.
 *
 * The same bytes are run twice, at a real path and with none, so the silence is
 * pinned to the missing path rather than to the source having nothing the sniff
 * reacts to: at its real path this exact file is a violation, asserted above and
 * re-asserted here so the two halves cannot drift apart.
 */
it('says nothing when the file has no path to resolve a companion from', function (): void {
    $fixture = fixturePath(sniffFixtureDirectory(REQUIRE_TEST_FILE), 'src/Untested.php');

    expect(warningTuples(analyzeWithSniffs([REQUIRE_TEST_FILE], $fixture)))->toBe([
        ['line' => 10, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);

    $piped = analyzeStdinSource([REQUIRE_TEST_FILE], (string) file_get_contents($fixture));

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});

/**
 * Writing the missing test is the fix, and no fixer can write it. Asserted
 * through getFixableCount() rather than violationFixableFlags(), which reads
 * errors only and would be empty here whatever the flag said.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Untested.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The message carries what a reader has to act on: which file has no test,
 * where one was looked for, the standard it comes from, and the boundary — this
 * is an existence check, and it says nothing about the order the code and its
 * test were written in, nor about what the test asserts.
 *
 * The expected path is asserted in full rather than by keyword, because it is
 * the one part of the message that is computed: a mapping bug shows up here
 * before it shows up anywhere else.
 */
it('names the file, the expected path, the standard, and the boundary', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(REQUIRE_TEST_FILE, 'src/Untested.php')->getWarnings());
    $message = $messages[10][0];
    $expectedPath = fixturePath(sniffFixtureDirectory(REQUIRE_TEST_FILE), 'tests/UntestedTest.php');

    expect($message)->toContain('Untested.php has none')
        ->and($message)->toContain($expectedPath)
        ->and($message)->toContain('docs/standards/testing-development-process-tdd.md')
        ->and($message)->toContain('#57')
        ->and($message)->toContain('Existence only');
});
