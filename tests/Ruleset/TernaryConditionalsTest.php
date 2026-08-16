<?php

/**
 * The "Conditionals: Ternary Conditionals" standard (#20) as wired into the
 * master rules.xml — Slevomat's ControlStructures.RequireTernaryOperator for
 * the if/else that only assigns or returns, plus the custom
 * CleanCode.Conditionals.DisallowNestedTernary sniff for the nesting half.
 *
 * The sniff's own behaviour is covered by
 * tests/Standards/DisallowNestedTernaryTest.php. What is asserted here is the
 * wiring: that both halves are reachable through rules.xml at once, at the
 * severity and fixability the standard claims, and that the custom sniff is
 * still needed at all.
 *
 * Every fixture is run through both sniffs together, and only those two, so a
 * sibling standard landing in rules.xml cannot shift the line map — the
 * fixtures deliberately carry if/else statements, which several other rules in
 * the master ruleset also speak about.
 *
 * Line numbers refer to tests/fixtures/_rulesets/TernaryConditionals/.
 */

declare(strict_types=1);

const TERNARY_CONDITIONALS_SNIFFS = [
    'CleanCode.Conditionals.DisallowNestedTernary',
    'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator',
];

const REQUIRE_TERNARY_SNIFF = 'SlevomatCodingStandard.ControlStructures.RequireTernaryOperator';

const TERNARY_NOT_USED = REQUIRE_TERNARY_SNIFF . '.TernaryOperatorNotUsed';

const NESTED_TERNARY_VIOLATION = 'CleanCode.Conditionals.DisallowNestedTernary.NestedTernary';

/**
 * Every report on violations.php, from both halves of the standard at once.
 *
 * - 4, 13 — the two shapes RequireTernaryOperator rewrites: an if/else whose
 *   branches only assign the same variable, and one whose branches only return.
 * - 21, 24, 28, 31 — nested ternaries, each at the nested operator.
 * - 35, 44 — the two shapes RequireTernaryOperator reports *without* rewriting:
 *   a comment inside a branch, and a condition using a word operator. Both are
 *   asserted below to be unfixable, which is what makes them distinct entries
 *   rather than a longer list of the same thing.
 */
const TERNARY_CONDITIONALS_VIOLATIONS = [
    ['line' => 4, 'column' => 1, 'source' => TERNARY_NOT_USED],
    ['line' => 13, 'column' => 5, 'source' => TERNARY_NOT_USED],
    ['line' => 21, 'column' => 34, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 24, 'column' => 32, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 28, 'column' => 34, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 31, 'column' => 33, 'source' => NESTED_TERNARY_VIOLATION],
    ['line' => 35, 'column' => 1, 'source' => TERNARY_NOT_USED],
    ['line' => 44, 'column' => 1, 'source' => TERNARY_NOT_USED],
];

/**
 * Fixability per report, in the order above. The two halves differ here and the
 * difference is the standard's, not an accident: RequireTernaryOperator ships a
 * fixer and rules.xml leaves it on, while the nesting half is detection only
 * because unfolding a nested ternary means naming an intermediate value.
 *
 * The false entries at 35 and 44 are the third state — reported by a fixable
 * sniff, but not fixable here, because rewriting would drop a comment or change
 * the precedence of a word operator. The docs claim that; this asserts it.
 */
const TERNARY_CONDITIONALS_FIXABLE = [true, true, false, false, false, false, false, false];

it('wires the require-ternary sniff into the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_TERNARY_SNIFF);
});

/**
 * The compliant fixture is discriminating on both halves: single-level
 * ternaries in every position the nesting sniff registers on, and the four
 * if/else shapes RequireTernaryOperator must leave alone — an if/elseif chain,
 * an if without an else, branches holding more than one statement, and branches
 * assigning different variables — plus its two documented blind spots, compound
 * assignment and a braceless if/else.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'compliant.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports both halves of the standard through the master ruleset', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'violations.php');

    expect(violationTuples($file))->toBe(TERNARY_CONDITIONALS_VIOLATIONS)
        ->and($file->getWarnings())->toBe([]);
});

it('offers an auto-fix for the convertible if/else only', function (): void {
    $file = analyzeRulesetFixture(TERNARY_CONDITIONALS_SNIFFS, 'TernaryConditionals', 'violations.php');

    expect(violationFixableFlags($file))->toBe(TERNARY_CONDITIONALS_FIXABLE)
        ->and($file->getFixableCount())->toBe(2);
});

/**
 * Why the nesting half is a custom sniff at all. Issue #20's acceptance
 * criteria name SlevomatCodingStandard.ControlStructures.DisallowNestedTernaryOperator,
 * which does not exist in slevomat/coding-standard 8.x — so the standard could
 * not be met by configuration and the sniff was written instead.
 *
 * That is a claim about a dependency, and a dependency upgrade can change it,
 * so it is asserted rather than left in a comment: the whole Slevomat standard
 * is loaded and the absent sniff looked for by name. If a future release adds
 * it, this fails and the custom sniff can be reconsidered — which is the
 * outcome worth being told about.
 *
 * Absence is asserted against the standard's own sniff catalogue rather than
 * against a silent run over the fixture, because Slevomat is *not* silent
 * there: DisallowShortTernaryOperator and RequireMultiLineTernaryOperator both
 * report on those lines. Neither says anything about nesting, so a
 * behaviour-based assertion would have to filter them out by name and would
 * then be asserting the same thing less directly.
 *
 * The sibling expectation is what keeps this from passing vacuously: a
 * standard that failed to load has no sniffs at all, and would satisfy the
 * absence check on its own.
 */
it('has no Slevomat sniff covering nested ternaries', function (): void {
    $file = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath('DisallowNestedTernarySniff', 'failing.php')
    );

    expect($file->ruleset->sniffCodes)
        ->not->toHaveKey('SlevomatCodingStandard.ControlStructures.DisallowNestedTernaryOperator')
        ->and($file->ruleset->sniffCodes)
        ->toHaveKey('SlevomatCodingStandard.ControlStructures.DisallowShortTernaryOperator');
});
