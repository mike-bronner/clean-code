<?php

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

it('reports every boolean-return if', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getWarnings()),
        static fn (array $sources): bool => in_array(USELESS_IF_CONDITION, $sources, true)
    ));

    expect($lines)->toBe([23, 33, 44]);
});

it('lowers the sniff from Slevomat error to warning', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    $errorSources = array_merge(...array_values(violationSourcesByLine($file->getErrors()) ?: [[]]));

    expect($errorSources)->not->toContain(USELESS_IF_CONDITION);
});

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

it('autofixes the fixable shapes into the fixed fixture', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/AvoidConditionals', 'boolean-return.fixed.php')));
});

it('still reports the unfixable shape after fixing', function () use ($booleanReturnFixture): void {
    $file = $booleanReturnFixture('boolean-return.fixed.php');

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getWarnings()),
        static fn (array $sources): bool => in_array(USELESS_IF_CONDITION, $sources, true)
    ));

    expect($lines)->toBe([36]);
});
