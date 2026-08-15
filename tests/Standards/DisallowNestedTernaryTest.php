<?php

/**
 * Behaviour of CleanCode.Conditionals.DisallowNestedTernary — the nesting half
 * of "Conditionals: Ternary Conditionals" (#20). The "prefer a ternary" half is
 * Slevomat's RequireTernaryOperator, wired in rules.xml and covered by
 * tests/Ruleset/TernaryConditionalsTest.php.
 *
 * Line and column numbers refer to tests/fixtures/DisallowNestedTernarySniff/.
 *
 * The columns are the point of the failing-fixture assertion, not decoration.
 * The sniff's contract is that every nesting is reported exactly once and *at
 * the inner operator* — line alone cannot tell that apart from a report at the
 * outer operator on the same line, which is what every nesting on one line
 * looks like. Both directions of the rule have been wrong at some point in this
 * sniff's history, so each is pinned by a fixture rather than by the docblock.
 */

declare(strict_types=1);

const NESTED_TERNARY_SNIFF = 'CleanCode.Conditionals.DisallowNestedTernary';

const NESTED_TERNARY = NESTED_TERNARY_SNIFF . '.NestedTernary';

/**
 * Every report on failing.php. One entry per nesting, each at the operator of
 * the *nested* ternary:
 *
 * - 4, 7, 10 — the three operand positions (then, else, condition).
 * - 13, 23, 26 — short-ternary chains: bare, wrapped in redundant grouping
 *   parentheses, and grouped then combined with "+". Each reports once, at the
 *   second operator; the chain head is never flagged.
 * - 17 — a multi-line nesting, reported on the line the nested operator is on
 *   rather than the line the statement starts on.
 * - 30, 31, 32 — a grouped ternary feeding another ternary's condition,
 *   compared, invoked, and added to. Grouping parentheses carry no nesting
 *   semantics, so what makes these violations is the outer ternary consuming
 *   the group, not the parentheses.
 * - 36, 42 — nesting inside a match arm's value and inside an arrow-function
 *   body. Both constructs bound a ternary they *contain*; neither exempts a
 *   ternary nested within that ternary.
 * - 48, 53 — the two shapes where an immediately-invoked arrow function meets
 *   a ternary. Line 48 nests inside the body and is reported there, once: the
 *   body's own outer operator is not nested by the ternary consuming the
 *   invocation. Line 53 nests a grouped ternary into another ternary's
 *   condition, with an invoked arrow function inside the inner condition.
 */
const NESTED_TERNARY_VIOLATIONS = [
    ['line' => 4, 'column' => 34, 'source' => NESTED_TERNARY],
    ['line' => 7, 'column' => 36, 'source' => NESTED_TERNARY],
    ['line' => 10, 'column' => 16, 'source' => NESTED_TERNARY],
    ['line' => 13, 'column' => 32, 'source' => NESTED_TERNARY],
    ['line' => 17, 'column' => 18, 'source' => NESTED_TERNARY],
    ['line' => 23, 'column' => 34, 'source' => NESTED_TERNARY],
    ['line' => 26, 'column' => 33, 'source' => NESTED_TERNARY],
    ['line' => 30, 'column' => 17, 'source' => NESTED_TERNARY],
    ['line' => 31, 'column' => 16, 'source' => NESTED_TERNARY],
    ['line' => 32, 'column' => 23, 'source' => NESTED_TERNARY],
    ['line' => 36, 'column' => 38, 'source' => NESTED_TERNARY],
    ['line' => 42, 'column' => 42, 'source' => NESTED_TERNARY],
    ['line' => 48, 'column' => 30, 'source' => NESTED_TERNARY],
    ['line' => 53, 'column' => 28, 'source' => NESTED_TERNARY],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NESTED_TERNARY_SNIFF);
});

/**
 * passing.php carries every construct the sniff registers on in its compliant
 * form, plus the near misses it must stay silent on — sibling ternaries split
 * by a comma, an array bracket, or a match arrow; a ternary bounded by a call
 * argument, an array element, or an arrow-function body; and the whole family
 * of immediately-invoked arrow functions whose result feeds another ternary.
 * Deleting the arrow-body boundary check in the sniff makes nine of those lines
 * report, so this assertion discriminates rather than passing on an empty file.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports every nesting at the nested operator', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe(NESTED_TERNARY_VIOLATIONS);
});

/**
 * Detection only: unfolding a nested ternary means naming an intermediate
 * value, which no fixer can invent. The error count sits beside the fixable
 * count because an empty report also has zero fixable violations — the count is
 * what stops this passing vacuously.
 */
it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(NESTED_TERNARY_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(NESTED_TERNARY_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
