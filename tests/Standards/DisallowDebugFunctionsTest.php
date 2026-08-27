<?php

/**
 * Tests the custom CleanCode.Debug.DisallowDebugFunctions sniff.
 *
 * Migrated from the PHP_CodeSniffer AbstractSniffUnitTest harness; the fixture
 * moved from CleanCode/Tests/Debug/DisallowDebugFunctionsUnitTest.inc to
 * tests/fixtures/DisallowDebugFunctionsSniff/failing.php, and the lines pinned
 * below carried over from that test's getErrorList(), with the column of each
 * report added. Lines 26 onward were added for the PHPMD
 * DevelopmentCodeFragment names (#86).
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
 * string, a property, a namespace prefix, a return-by-reference declaration, an
 * attribute, an instantiation behind a leading qualifier, and a `use function`
 * import. Each of those is one of the shared FunctionCalls helper's exclusions,
 * so the fixture's silence is a verdict about them rather than merely the
 * absence of a debug call.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every column is the function-name token's own position, never the qualifier
 * in front of it: column 1 for a plain call, column 2 where a leading `\`
 * precedes the name, and column 11 on line 22, past `namespace\`.
 */
it('flags every debug call at its own line', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found',
        ],
        [
            [3, 1], [4, 1], [5, 1], [6, 1], [7, 1],
            // `namespace\dump()` where no namespace is declared: the namespace
            // in force is the global one, so the call reaches PHP's own
            // function exactly as the leading-separator form on the next line
            // does.
            [22, 11],
            [24, 2], [26, 1], [27, 2], [28, 1], [29, 1], [30, 2], [31, 1],
            [32, 2],
        ]
    ))->and($file->getWarnings())->toBe([]);
});

/**
 * A `use function` import binds only the names it lists. passing.php pins the
 * quiet side — every listed name goes unreported, the first of a two-name list
 * as much as the last — and this pins the loud side, which is where an
 * over-eager import check would show: every other debug call in the same file
 * stays flagged, and so does the *source* name of an aliased import, because
 * the alias is what the import actually bound.
 */
it('flags every debug call an import did not bind', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'imported-names.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
        ['line' => 14, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
        ['line' => 15, 'column' => 1, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The other half of the relative-qualifier rule. failing.php pins that
 * `namespace\dump()` is PHP's own function where no namespace is declared; this
 * pins that the same spelling is a different symbol once one is, and that the
 * declaration is what makes the difference — a leading-separator call in the
 * same file is still flagged.
 */
it('stays silent on a namespace-relative call inside a declared namespace', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'namespace-relative.php');

    expect(violationTuples($file))
        ->toBe([['line' => 22, 'column' => 2, 'source' => DISALLOW_DEBUG_FUNCTIONS . '.Found']])
        ->and($file->getWarnings())->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DISALLOW_DEBUG_FUNCTIONS, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
