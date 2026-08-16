<?php

/**
 * Tests the custom CleanCode.Testing.TestSuiteNamespace sniff (Testing: Test
 * Suites, #60). Fixtures live in tests/fixtures/TestSuiteNamespaceSniff/. The
 * rule is detection-only, so there is no autofixed fixture.
 *
 * The sniff compares two statements about which suite a test belongs to: the
 * segment directly below the `tests` root in its declared namespace, and the
 * segment directly below the `tests` root in the path of the file it sits in.
 * That second half is why this directory holds more than the two flat fixtures.
 * The contract's passing.php and failing.php have fixed names *and* a fixed
 * location, and that location puts `fixtures` directly below the nearest test
 * root — which names no suite — so they can only ever exercise the namespace
 * side. The path side needs fixtures whose real paths carry a suite segment,
 * which is where the nested trees under this directory come from:
 *
 * - tests/Unit/ and tests/Feature/ — the compliant pair for each suite, the
 *   mismatch cases, and the shapes that are never flagged despite sitting under
 *   a suite directory;
 * - tests/Feature/tests/Unit/ — a second, decoy test root above the real one,
 *   for the ancestor-anchoring case;
 * - tests/Contract/ — a suite name the shipped configuration does not carry,
 *   for the property-override case.
 *
 * They are ordinary in-repo fixtures, covered by composer lint's fixtures
 * ignore pattern like every other one, and invisible to the contract sweep,
 * which only looks for the three fixed names.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const TEST_SUITE_NAMESPACE = 'CleanCode.Testing.TestSuiteNamespace';

const TEST_SUITE_NAMESPACE_DIRECTORY = TEST_SUITE_NAMESPACE . '.DirectoryMismatch';

const TEST_SUITE_NAMESPACE_NAMESPACE = TEST_SUITE_NAMESPACE . '.NamespaceMismatch';

const TEST_SUITE_UNIT_FIXTURES = 'tests/Unit/';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TEST_SUITE_NAMESPACE);
});

/**
 * Every shape in passing.php stays silent, and each one pins a different reason
 * for that silence. Each line below is the `class` declaration itself:
 *
 * - line 14, `Tests\Support\HelperTest` — both sides agree that no suite is
 *   named. The sniff registers on it, resolves a test root on the namespace
 *   side and compares; it is not silent for want of anything to look at.
 * - line 24, `App\Domain\Feature\ToggleTest` — a `Feature` segment with no test
 *   root above it, so the class sits outside the test tree. Here it is only a
 *   negative control: this fixture's own path names no suite either, so the two
 *   sides agree at `null` and the same silence survives more than one way of
 *   breaking the rule. The discriminating version of this shape is
 *   tests/Unit/business-domain-namespace.php, below.
 * - line 32, `Tests\UnitOfWork\LedgerTest` — a segment that merely starts with a
 *   suite name. Segments are compared whole, so this is left alone.
 * - line 40, `Tests\BootstrapTest` — a class directly on the test root. Nothing
 *   follows the root, so no suite is named, which is a different answer from
 *   "not a test at all" and still has to be silent.
 * - line 49, `Tests\Unit\SupportHelper` — a suite namespace, but not a test
 *   class: no `Test` suffix and no configured base class.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php sits at a path naming no suite, so every suite-namespaced test in
 * it is misplaced. Each line pins a distinct resolution:
 *
 * - lines 11, 17 and 23 — the three shipped suite names, one class each, so no
 *   single spelling can stand in for the others.
 * - line 32, `TESTS\UNIT` — the test root *and* the suite compared
 *   case-insensitively. Match the root literally and this namespace carries no
 *   test root, the class is vetoed, and the violation is never reported.
 * - line 42, `class Reports extends TestCase` — recognized as a test only by
 *   what it extends; it carries no `Test` suffix.
 * - line 51, `class Invoices extends \PHPUnit\Framework\TestCase` — the same
 *   route with the parent fully qualified, which is what makes the sniff's
 *   trailing-segment comparison load-bearing rather than decorative.
 * - lines 61 and 69 — T_NAMESPACE is also the `namespace\` relative-name
 *   operator. The nearest one above line 69 is that operator inside the
 *   previous class's body; read as a declaration it resolves ManifestTest to a
 *   namespace of `formatted()`, which carries no test root, and line 69 goes
 *   unreported.
 *
 * The column is 5 rather than 1 because braced namespace blocks indent their
 * class declarations; the violation is reported at the `class` keyword.
 */
it('flags every suite namespace with no matching directory', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 17, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 23, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 32, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 42, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 51, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 61, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 69, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);
});

/**
 * The other direction, reachable only from a fixture whose real path carries a
 * suite segment. Both ways of contradicting the location are here:
 *
 * - line 8, `Tests\Feature\ReportTest` under tests/Unit/ — names a different
 *   suite.
 * - line 15, `Tests\Support\LedgerTest` under tests/Unit/ — names no suite at
 *   all while still sitting inside the test tree, which is the branch where the
 *   namespace side resolves to a tail that carries no suite rather than to
 *   nothing.
 */
it('flags a test under a suite directory whose namespace disagrees', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, TEST_SUITE_UNIT_FIXTURES . 'suite-mismatch.php');

    expect(warningTuples($file))->toBe([
        ['line' => 8, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
        ['line' => 15, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
    ]);
});

/**
 * The compliant path shape, one file per suite. Asserting silence is only
 * meaningful where nothing else in the file is speaking, so each of these is its
 * own file rather than another block in a shared one. Two suites rather than
 * one, so no single suite name can stand in for the whole configured list.
 */
it('leaves a test whose namespace matches its suite directory alone', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'the unit suite' => TEST_SUITE_UNIT_FIXTURES . 'suite-compliant.php',
    'the feature suite' => 'tests/Feature/suite-compliant.php',
]);

/**
 * PHP_CodeSniffer hands the sniff a fully resolved absolute path, so every
 * directory above the project is part of what gets read — including whatever the
 * checkout happens to sit under. This package is distributed for other
 * repositories to require, so that location is not under its control.
 *
 * tests/Feature/tests/Unit/nested-root.php carries two `tests` roots in one
 * path. The root closest to the file governs, so the suite read here is `Unit`
 * and the declared namespace agrees. Anchor on the first root instead and the
 * segment directly below it is `Feature`, which contradicts this namespace and
 * reports NamespaceMismatch on a correctly filed test.
 */
it('does not anchor the test root on an ancestor directory', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'tests/Feature/tests/Unit/nested-root.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The suite is the segment *directly* below the test root, on both sides — not
 * a suite name found anywhere below it. A suite name deeper down is that
 * suite's own structure, and reading it as the filing would report correctly
 * filed helpers:
 *
 * - tests/Support/Unit/deep-path-suite.php declares Tests\Support. Search the
 *   whole path below the root and it resolves to `Unit` while the namespace
 *   still resolves to none, reporting NamespaceMismatch.
 * - tests/Support/deep-namespace-suite.php declares Tests\Support\Unit. Search
 *   the whole namespace below the root and it resolves to `Unit` while the path
 *   resolves to none, reporting DirectoryMismatch.
 *
 * One fixture per side, because a single call site reads both and either half
 * could regress alone.
 */
it('reads the suite directly below the root, not anywhere below it', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a suite name deeper in the path' => 'tests/Support/Unit/deep-path-suite.php',
    'a suite name deeper in the namespace' => 'tests/Support/deep-namespace-suite.php',
]);

/**
 * The shapes that sit under a suite directory and are still never flagged. Each
 * one declares the *wrong* suite for the directory it is in, so each would be
 * reported the moment its exemption stopped working — silence here is pinned to
 * the exemption rather than to the fixture having nothing to disagree about.
 *
 * Each is its own file for the same reason: one file asserting five silences
 * cannot say which of the five it lost.
 *
 * - the support helper — no `Test` suffix and no configured base class, so it is
 *   not a test class at all.
 * - the trait and the interface — named with the `Test` suffix, so only their
 *   token type keeps them out. Registering T_TRAIT or T_INTERFACE would report
 *   both.
 * - the abstract class — a T_CLASS *with* the suffix, so only the modifier keeps
 *   it out. A shared abstract test case is exercised through the concrete tests
 *   extending it and lives wherever those can reach it.
 * - the class with no namespace — the rule compares two declared suites and this
 *   file only carries one, so there is nothing to contradict the location.
 * - the business-domain namespace — `App\Domain\Feature` spells a suite name but
 *   carries no test root, so the class is outside the test tree. This is the
 *   case root-anchoring exists for: let the path speak anyway and a domain class
 *   is told to declare a `Unit` segment it has no business carrying.
 */
it('leaves a non-test declaration under a suite directory alone', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, TEST_SUITE_UNIT_FIXTURES . $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a shared support helper' => 'support-helper.php',
    'a trait' => 'test-trait.php',
    'an interface' => 'test-interface.php',
    'an abstract test case' => 'abstract-test-case.php',
    'a class with no declared namespace' => 'no-namespace.php',
    'a business-domain namespace spelling a suite name' => 'business-domain-namespace.php',
]);

/**
 * The suite names are the consuming project's to choose, and the property is
 * only meaningfully configurable if changing it changes what the sniff reports.
 *
 * tests/Contract/custom-suite.php declares Tests\Unit under a tests/Contract/
 * directory, and reports under *both* configurations — differently. With the
 * shipped segments the location names no suite, so only the namespace speaks and
 * the violation is DirectoryMismatch; add `Contract` to the segments and the
 * location speaks too, contradicting the namespace, and the same line reports
 * NamespaceMismatch instead. A fixture that merely fell silent could not tell an
 * applied property from a sniff that had stopped looking.
 *
 * Set through Ruleset::setSniffProperty() rather than by assignment, so this
 * pins the path a consuming ruleset's <property> element actually takes.
 */
it('reports against the configured suite segments', function (): void {
    $fixture = 'tests/Contract/custom-suite.php';

    $shipped = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect(warningTuples($shipped))->toBe([
        ['line' => 12, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);

    $configured = analyzeFixtureWithRulesetProperties(
        TEST_SUITE_NAMESPACE,
        $fixture,
        ['suiteSegments' => ['Contract', 'Unit']]
    );

    expect(warningTuples($configured))->toBe([
        ['line' => 12, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
    ]);
});

/**
 * The test root is folded to lower case on the way in, so a project that spells
 * the property the way its namespace spells it — `Tests` rather than `tests` —
 * gets the same answer. The segments it is compared against are already
 * lowered, so this is the one place the *configured* value's casing matters.
 *
 * failing.php under a `TESTS` root reports exactly what it reports under the
 * shipped `tests`. Drop the fold and the root matches nothing, every class is
 * vetoed as sitting outside the test tree, and the file falls silent.
 */
it('matches the configured test root case-insensitively', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        TEST_SUITE_NAMESPACE,
        'failing.php',
        ['testRoot' => 'TESTS']
    );

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 17, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 23, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 32, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 42, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 51, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 61, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 69, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);
});

/**
 * Moving the file and renaming its namespace are both valid ways to reconcile a
 * mismatch, and which one is right depends on the project's layout rather than
 * on anything in the file — so the sniff never offers a fix.
 *
 * Both fixtures are checked, because the two violation codes are raised at
 * separate call sites: asserting only on failing.php would leave
 * NamespaceMismatch free to hand out a fix nobody wrote.
 */
it('marks no violation fixable', function (string $fixture, int $expected): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getWarningCount())->toBe($expected)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    ['failing.php', 8],
    [TEST_SUITE_UNIT_FIXTURES . 'suite-mismatch.php', 2],
]);

/**
 * Piped input with no --stdin-path gives PHPCS the file name STDIN, so there is
 * no location for the namespace to disagree with — an editor linting a buffer
 * that way would otherwise get every suite-namespaced test reported as
 * misplaced.
 *
 * The same bytes are run twice, at a real path and with none, so the silence is
 * pinned to the missing path rather than to the source having nothing the sniff
 * reacts to: at a real path this exact source is a violation.
 */
it('says nothing when the file has no path to compare against', function (): void {
    $source = <<<'PHP'
        <?php

        namespace Tests\Unit;

        class CalculatorTest
        {
        }
        PHP;

    $onDisk = analyzeWithSniffs(
        [TEST_SUITE_NAMESPACE],
        stageSourceOutsideTests($source, 'CalculatorTest.php')
    );

    expect(warningTuples($onDisk))->toBe([
        ['line' => 5, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);

    $piped = analyzeStdinSource([TEST_SUITE_NAMESPACE], $source);

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});
