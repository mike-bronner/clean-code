<?php

/**
 * Tests the custom CleanCode.Debug.DisallowDebugFunctions sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the line =>
 * error-count map below is preserved verbatim from that test's getErrorList().
 * The fixture moved from CleanCode/Tests/Debug/DisallowDebugFunctionsUnitTest.inc
 * to tests/fixtures/DisallowDebugFunctionsSniff/failing.php.
 *
 * The rule is detection-only — removing a debug call is a judgement about what
 * the code was meant to do — so there is no autofixed fixture, and the
 * detection-only test pins that.
 */

declare(strict_types=1);

const DISALLOW_DEBUG_FUNCTIONS = 'CleanCode.Debug.DisallowDebugFunctions';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_DEBUG_FUNCTIONS);
});

/**
 * passing.php pairs ordinary debug-free code with every near-miss shape the
 * sniff must stay silent on — the debug names reached through an object
 * operator, a nullsafe operator, a double colon, a declaration, `new`, a
 * string, a property, and a namespace prefix. Each of those is one of the
 * sniff's early returns, so the fixture's silence is a verdict about them
 * rather than merely the absence of a debug call.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every debug call at its own line', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toBe([
        3 => 1,
        4 => 1,
        5 => 1,
        6 => 1,
        7 => 1,
        24 => 1,
    ])->and($file->getWarnings())->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
