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
 * sibling standards land in rules.xml.
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
 * Each of the three violation codes is pinned to its own line, in a
 * different enclosing construct: a control-structure parenthesis (7, 13),
 * an array literal (11) and an index (17).
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        7 => [NOT_OPERATOR_SPACING . '.NoSpaceAfter'],
        11 => [NOT_OPERATOR_SPACING . '.SpaceBefore'],
        13 => [NOT_OPERATOR_SPACING . '.TooMuchSpaceAfter'],
        17 => [NOT_OPERATOR_SPACING . '.SpaceBefore'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(NOT_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});
