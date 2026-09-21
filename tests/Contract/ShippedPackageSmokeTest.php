<?php

/**
 * The shipped, installed package, exercised end to end for every custom sniff.
 *
 * Every other contract test here drives PHP_CodeSniffer in process through
 * ConfigDouble, which blanks the CodeSniffer.conf Composer wrote at install
 * time and has tests/Helpers.php hand the installed standards back. That
 * harness settles what a sniff measures and nothing about whether the package a
 * consumer installs works: the scaffolding supplies the registration Composer
 * would have supplied, so a package that never registered itself passes all the
 * same. These tests use none of it. They execute the real vendor/bin/phpcs as a
 * separate process, from a working directory outside the package, against
 * the installed standard by name — `--standard=CleanCode`, exactly what the
 * README tells a consumer to write.
 *
 * The name rather than a path to the ruleset, deliberately. A path resolves
 * whether or not the package ever registered itself, so a sweep built on one
 * would keep passing through the precise failure these tests exist to catch.
 * The name resolves only through PHP_CodeSniffer's installed_paths, which only
 * the dealerdirect installer writes. Every sniff below is therefore also a
 * statement that the standard is really installed.
 *
 * That the name reaches the ruleset in this repository, rather than some other
 * CleanCode/ on the machine, is pinned separately in
 * tests/Contract/InstalledStandardTest.php.
 *
 * Both directions are asserted for every covered sniff, because either half
 * alone is satisfiable by a mechanism that never works:
 *
 * - failing.php has to report, with every message's source under the sniff's
 *   own code and at the sniff's own severity;
 * - passing.php has to report nothing and exit 0. This is the negative control.
 *   Without it a shell-out that always reported — or a stub that returned a
 *   canned message — would pass every positive assertion in the file.
 *
 * The coverage is derived from SWEPT_SNIFFS and SWEPT_WARNING_SNIFFS in
 * tests/Sniffs.php rather than listed here, so a CleanCode.* sniff added to
 * those datasets is swept from the moment it lands and this gap cannot reopen
 * one sniff at a time. Third-party entries in those same datasets are out of
 * scope: this package configures Generic.*, SlevomatCodingStandard.*,
 * Squiz.PHP.Eval and VariableAnalysis.* but did not author them, and their own
 * suites cover their own shipping.
 *
 * The seven path-scoped custom sniffs are not in those datasets at all —
 * scoping is decided from the file's real location, whether by
 * PHP_CodeSniffer's own patterns or by the sniff's own path property, so they
 * cannot be driven from tests/fixtures/ in place. Each carries the same
 * shipped-install smoke test in its own file instead:
 * tests/Standards/DisallowExternalPersistenceCallsTest.php,
 * tests/Standards/NoProceduralCodeTest.php,
 * tests/Standards/DisallowChainedPropertyFetchTest.php,
 * tests/Standards/DisallowNonResourceRoutesTest.php,
 * tests/Standards/UnitTestExternalConcernsTest.php,
 * tests/Standards/NoInternetTraversalTest.php and
 * tests/Standards/NonInvokableSpecialActionTest.php.
 *
 * ---
 *
 * On exit statuses, which the acceptance criteria describe as 2 for an
 * error-reporting sniff and 1 for a warning-reporting one. That is not what
 * PHP_CodeSniffer 3.13.6 does, measured and then confirmed against
 * Runner::runPHPCS() in the vendored source: the status turns on *fixability*,
 * not severity. 0 when nothing was reported, 1 when something was and none of
 * it is fixable, 2 when something was and some of it is fixable. An error-only
 * run and a warning-only run both exit 1 — CleanCode.Naming.ShortVariable's
 * failing fixture (25 errors, 0 fixable) and CleanCode.Testing.NoReflectionAccess's
 * (17 warnings, 0 fixable) both do — while CleanCode.WhiteSpace.BlankLines's
 * (25 errors, 25 fixable) exits 2.
 *
 * What the criteria are after is that the status be pinned exactly rather than
 * checked for being non-zero, so that 3 — the DeepExitException status a broken
 * install exits with, confirmed live against an uninstalled standard — can
 * never read as a pass. That is met here, by the contract phpcs actually has:
 * the expected status is derived per sniff from AUTOFIXABLE_SNIFFS, so 3 (and 0)
 * fail every positive assertion. Severity is pinned too, but directly, on each
 * message's own type field, which says ERROR or WARNING outright instead of
 * inferring it from a status that does not carry it.
 */

declare(strict_types=1);

// The enumerations these datasets derive from, the exclusion list, and
// expectedFailingStatus() all live in tests/Sniffs.php, which the bootstrap
// loads: a declaration made by one test file is not reliably in place when
// another test file's data providers are resolved, and PSR-1 will not have a
// test file both declare symbols and run the it() calls that are its point.
dataset('shipped error sniffs', shippedSmokeSniffs(SWEPT_SNIFFS));

dataset('shipped warning sniffs', shippedSmokeSniffs(SWEPT_WARNING_SNIFFS));

dataset('every shipped sniff', array_merge(
    shippedSmokeSniffs(SWEPT_SNIFFS),
    shippedSmokeSniffs(SWEPT_WARNING_SNIFFS)
));

dataset('shipped smoke exclusions', SHIPPED_SMOKE_EXCLUSIONS);

it('reports an error on its failing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'failing.php');

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.')
        ->and(array_unique(array_column($run['messages'], 'type')))->toBe(['ERROR'])
        ->and($run['status'])->toBe(expectedFailingStatus($sniffCode));
})->with('shipped error sniffs');

/**
 * The warning-level half of the same floor, asserted on the message type rather
 * than on the status, which does not distinguish the two. A sniff that had
 * quietly been promoted to an error would pass the positive half of the test
 * above and fails here.
 */
it('reports a warning on its failing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'failing.php');

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.')
        ->and(array_unique(array_column($run['messages'], 'type')))->toBe(['WARNING'])
        ->and($run['status'])->toBe(expectedFailingStatus($sniffCode));
})->with('shipped warning sniffs');

/**
 * The negative control. Silence *and* status 0 together, because each covers
 * what the other cannot: an empty message list is also what a run that never
 * reached the file produces, and a status of 0 is also what a run reporting
 * only some other sniff's violations would never produce but an unparsed report
 * would be read as. installedPhpcsRun() throws rather than returning an empty
 * list whenever the report cannot be read at all, which is what makes this
 * assertion a statement about the sniff.
 */
it('stays silent on its passing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'passing.php');

    expect($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
})->with('every shipped sniff');

/**
 * Nothing this package ships falls outside the sweep, and the one way out of it
 * cannot widen unnoticed.
 *
 * Two assertions, closing the two different ways coverage can shrink:
 *
 * - The derivation. Every CleanCode.* entry in the swept datasets is either
 *   swept here or named as an exclusion. This holds by construction while the
 *   datasets above are derived from the swept lists, and is not a second
 *   opinion on them; what it guards is the derivation itself, which a future
 *   edit replacing shippedSmokeSniffs() with a hand-written list — the shape
 *   this issue exists because of — would break while passing every per-sniff
 *   test above.
 * - The exclusion list, pinned to its literal contents. This is the hole the
 *   derivation leaves: an entry added to SHIPPED_SMOKE_EXCLUSIONS removes a
 *   sniff from the sweep and still satisfies the derivation, because the
 *   exclusions are added back into the total. Restating the list is the only
 *   thing that makes widening it a deliberate act — an edit that has to be made
 *   twice, in view of the comment saying what an exclusion has to be worth.
 */
it('sweeps every custom sniff the swept datasets carry', function (): void {
    $swept = array_values(array_filter(
        array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS),
        static fn (string $code): bool => str_starts_with($code, 'CleanCode.')
    ));

    $reached = array_merge(
        shippedSmokeSniffs(SWEPT_SNIFFS),
        shippedSmokeSniffs(SWEPT_WARNING_SNIFFS),
        SHIPPED_SMOKE_EXCLUSIONS
    );

    sort($swept);
    sort($reached);

    expect($reached)->toBe($swept)
        ->and(SHIPPED_SMOKE_EXCLUSIONS)->toBe(['CleanCode.Metrics.CyclomaticComplexity']);
});

/**
 * An exclusion is only ever legitimate while the sniff it names is still swept
 * and still reaches the shipped binary in both directions. All three are checked
 * here rather than taken on the comment's word, so an entry left behind by a
 * sniff that was renamed, retired, or quietly stopped being registered fails
 * instead of shrinking the sweep by one.
 *
 * Both directions, not just the failing one, because one direction is exactly
 * what an exclusion must not be able to buy its way out with: the sweep this
 * list removes a sniff from asserts the pair, and a sniff covered elsewhere by
 * a positive assertion alone is covered by something weaker than what it left.
 * That gap was real — CleanCode.Metrics.CyclomaticComplexity carried only the
 * positive half when it was first excluded here.
 *
 * The negative half deliberately restates what the excluded sniff's own file
 * also asserts, because the two answer different questions: that file covers
 * that sniff, this one holds the bar every future entry has to clear. An
 * exclusion whose sole coverage was later weakened or deleted elsewhere fails
 * here regardless. The positive half stays thin — what the sniff reports, line
 * by line, is its own test's subject and is not restated.
 */
it('keeps every excluded sniff justified', function (string $sniffCode): void {
    $failing = installedSniffFixtureRun($sniffCode, 'failing.php');
    $passing = installedSniffFixtureRun($sniffCode, 'passing.php');

    expect(array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS))->toContain($sniffCode)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0)
        ->and(array_column($failing['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.');
})->with('shipped smoke exclusions');

/**
 * The binary these tests claim to run has to be there. Asserted rather than
 * detected: a suite that skipped this layer when vendor/bin/phpcs was missing
 * would report green having proved nothing, which is the failure mode the whole
 * file exists to close. CI installs it before running the suite, so its absence
 * is a broken checkout and belongs in the failure column.
 */
it('runs against a phpcs binary that is really installed', function (): void {
    expect(is_file(cleanCodeRoot() . '/vendor/bin/phpcs'))->toBeTrue();
});

/**
 * A run that never happened must not read as silence.
 *
 * Every "stays silent" assertion above is an empty message list, and an empty
 * message list is exactly what a broken invocation would hand back if the
 * helper let it. It does not: phpcs answers an uninstalled standard by exiting
 * 3 with a plain-text error and no JSON at all, and installedPhpcsRun() throws
 * on unreadable output rather than returning nothing. Pinned here against a
 * standard path that cannot exist, so the negative control above is a statement
 * about the sniff rather than about the harness.
 */
it('throws rather than reporting silence when phpcs cannot run', function (): void {
    installedPhpcsRun(
        sys_get_temp_dir() . '/cleancode-standard-that-cannot-exist.xml',
        fixturePath('ShortClassNameSniff', 'passing.php')
    );
})->throws(RuntimeException::class);
