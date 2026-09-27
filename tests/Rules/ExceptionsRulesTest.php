<?php

declare(strict_types=1);

const REFERENCE_THROWABLE_ONLY = 'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly';

const REQUIRE_NON_CAPTURING_CATCH = 'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch';

it('registers both exception rules in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFERENCE_THROWABLE_ONLY)
        ->and($ruleset->sniffCodes)->toHaveKey(REQUIRE_NON_CAPTURING_CATCH);
});

it('produces no violations on the compliant Throwable fixture', function (): void {
    $file = analyzeFixture(REFERENCE_THROWABLE_ONLY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

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
