<?php

/**
 * Evaluates the Slevomat rules wired into the master CleanCode/ruleset.xml for the
 * Exceptions standard: catches must reference \Throwable rather than the
 * general \Exception, and a caught variable that is never used must be
 * dropped (non-capturing catch).
 *
 * Coverage comes in two parts. A wiring test parses the master CleanCode/ruleset.xml
 * through PHPCS's real ruleset path and asserts both rules are registered,
 * so dropping or misspelling a <rule ref> breaks the build. The behaviour
 * tests then pin each sniff in isolation against its fixture, keeping the
 * line maps and auto-fix output independent of other rules that land in the
 * master ruleset later.
 *
 * Each sniff owns a fixture directory named for its class, so both follow the
 * ordinary per-sniff contract: tests/fixtures/ReferenceThrowableOnlySniff/ and
 * tests/fixtures/RequireNonCapturingCatchSniff/, each with failing.php and its
 * expected phpcbf output autofixed.php.
 */

declare(strict_types=1);

const REFERENCE_THROWABLE_ONLY = 'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly';

const REQUIRE_NON_CAPTURING_CATCH = 'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch';

it('registers both exception rules in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFERENCE_THROWABLE_ONLY)
        ->and($ruleset->sniffCodes)->toHaveKey(REQUIRE_NON_CAPTURING_CATCH);
});

/**
 * Each sniff gets its own compliant fixture in its own directory, so neither
 * rule's passing assertion can be satisfied by the other's fixture. The
 * ReferenceThrowableOnly one deliberately includes catches of specific types
 * (\RuntimeException, an application exception) alongside \Throwable: the rule
 * is about referencing the *general* \Exception, not about mandating \Throwable
 * everywhere, and a fixture of nothing but \Throwable would not show that.
 */
it('produces no violations on the compliant Throwable fixture', function (): void {
    $file = analyzeFixture(REFERENCE_THROWABLE_ONLY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The compliant fixture pairs non-capturing catches with capturing ones whose
 * variable *is* used — including uses that are easy to miss, a rethrow and a
 * closure `use` — since the rule only fires when a capture goes unreferenced.
 */
it('produces no violations on the compliant non-capturing fixture', function (): void {
    $file = analyzeFixture(REQUIRE_NON_CAPTURING_CATCH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags general exception catches', function (): void {
    $file = analyzeFixture(REFERENCE_THROWABLE_ONLY, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        6 => [REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException'],
        13 => [REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException'],
        20 => [REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException'],
        62 => [REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException'],
        73 => [REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException'],
    ]);
});

it('fixes a general exception catch to \Throwable', function (): void {
    $file = analyzeFixture(REFERENCE_THROWABLE_ONLY, 'failing.php');

    expect($file->getFixableCount())->toBe($file->getErrorCount())
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ReferenceThrowableOnlySniff', 'autofixed.php')));
});

it('flags unused catch captures', function (): void {
    $file = analyzeFixture(REQUIRE_NON_CAPTURING_CATCH, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        20 => [REQUIRE_NON_CAPTURING_CATCH . '.NonCapturingCatchRequired'],
        27 => [REQUIRE_NON_CAPTURING_CATCH . '.NonCapturingCatchRequired'],
        42 => [REQUIRE_NON_CAPTURING_CATCH . '.NonCapturingCatchRequired'],
        53 => [REQUIRE_NON_CAPTURING_CATCH . '.NonCapturingCatchRequired'],
    ]);
});

it('fixes unused catch captures', function (): void {
    $file = analyzeFixture(REQUIRE_NON_CAPTURING_CATCH, 'failing.php');

    expect($file->getFixableCount())->toBe($file->getErrorCount())
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('RequireNonCapturingCatchSniff', 'autofixed.php')));
});
