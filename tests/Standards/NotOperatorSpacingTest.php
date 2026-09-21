<?php

/**
 * Tests the custom CleanCode.Operators.NotOperatorSpacing sniff (Arrays:
 * Operator spacing & line breaks, #35). Fixtures live in
 * tests/fixtures/NotOperatorSpacingSniff/ and follow the three-fixture
 * contract: passing.php is clean, failing.php carries one instance of each
 * violation code, and autofixed.php is phpcbf's output for failing.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const NOT_OPERATOR_SPACING = 'CleanCode.Operators.NotOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NOT_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Each of the three violation codes is pinned to its own line and column, in a
 * different enclosing construct: a control-structure parenthesis (7, 13),
 * an array literal (11) and an index (17).
 *
 * Every column is the `!` token's own position, so it also pins where inside
 * the construct each report lands: column 5 inside `if (`, column 12 inside the
 * array literal and column 17 inside the index.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => NOT_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 11, 'column' => 12, 'source' => NOT_OPERATOR_SPACING . '.SpaceBefore'],
        ['line' => 13, 'column' => 5, 'source' => NOT_OPERATOR_SPACING . '.TooMuchSpaceAfter'],
        ['line' => 17, 'column' => 17, 'source' => NOT_OPERATOR_SPACING . '.SpaceBefore'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});
