<?php

/**
 * Tests the SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn
 * configuration in the master CleanCode/ruleset.xml — the auto-fixable slice of
 * "Conditionals: Avoid Conditionals" (#12).
 *
 * Two things are configuration, not sniff behaviour, and both are pinned here:
 * that the sniff is wired in at all, and that its type is lowered from
 * Slevomat's error to a warning so the whole standard speaks at one severity.
 * A test that only counted violations would pass identically with the <type>
 * override dropped.
 *
 * The fixture is run through the *whole* master ruleset, so the assertions are
 * scoped to this one source; the sibling CleanCode.Conditionals.AvoidConditionals
 * sniff also speaks about the same lines, and unrelated PSR rules the fixture
 * trips must not mask what is asserted here.
 *
 * Line numbers refer to tests/fixtures/_rulesets/AvoidConditionals/boolean-return.php.
 */

declare(strict_types=1);

const USELESS_IF_CONDITION
    = 'SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn.UselessIfCondition';

$booleanReturnFixture = static fn (string $fixture) => analyzeWithMasterRuleset(
    fixturePath('_rulesets/AvoidConditionals', $fixture)
);

it('wires the useless-if-condition sniff into the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)
        ->toHaveKey('SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn');
});

/**
 * Both fixable shapes (23: early return, 33: if/else) and the unfixable one
 * (44: a truthy string condition) are reported. Nothing else in the fixture is.
 */
it('reports every boolean-return if', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getWarnings()),
        static fn (array $sources): bool => in_array(USELESS_IF_CONDITION, $sources, true)
    ));

    expect($lines)->toBe([23, 33, 44]);
});

/**
 * The <type>warning</type> override in CleanCode/ruleset.xml. Slevomat reports this sniff
 * as an error out of the box, so without the override these violations would
 * appear in getErrors() and fail a consumer's build over an advisory standard.
 */
it('lowers the sniff from Slevomat error to warning', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    $errorSources = array_merge(...array_values(violationSourcesByLine($file->getErrors()) ?: [[]]));

    expect($errorSources)->not->toContain(USELESS_IF_CONDITION);
});

/**
 * The half of the standard that says "forms without a safe mechanical fix are
 * warned only". Slevomat marks the violation fixable only when the condition
 * already evaluates to a boolean, so `hasName()` on line 44 — whose condition
 * is a string — is reported but left alone. Collapsing it to `return $name;`
 * would widen the declared bool return type to string.
 */
it('marks only the type-safe shapes fixable', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    $fixable = [];

    foreach ($file->getWarnings() as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                if ($violation['source'] === USELESS_IF_CONDITION && $violation['fixable'] === true) {
                    $fixable[] = $line;
                }
            }
        }
    }

    expect($fixable)->toBe([23, 33]);
});

/**
 * phpcbf's real output, not a paraphrase of it — including the negation on
 * line 33's if/else form, whose if-branch returns false.
 */
it('autofixes the fixable shapes into the fixed fixture', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/AvoidConditionals', 'boolean-return.fixed.php')));
});

/**
 * The unfixable shape survives the fixer, so running phpcbf never silently
 * loses a warning it could not honestly resolve.
 */
it('still reports the unfixable shape after fixing', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.fixed.php');

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getWarnings()),
        static fn (array $sources): bool => in_array(USELESS_IF_CONDITION, $sources, true)
    ));

    expect($lines)->toBe([36]);
});
