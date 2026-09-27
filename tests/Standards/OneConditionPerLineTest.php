<?php

declare(strict_types=1);

const ONE_CONDITION_PER_LINE = 'CleanCode.Conditionals.OneConditionPerLine';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ONE_CONDITION_PER_LINE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 68, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 75, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 84, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 89, 'column' => 15, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 90, 'column' => 17, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 97, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 104, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 110, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 117, 'column' => 3, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ['line' => 124, 'column' => 3, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 129, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
    ])->and($file->getWarnings())->toBe([]);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('OneConditionPerLineSniff', 'autofixed.php')));
});

it('reports but does not fix a split single condition wrapping a comment', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'autofixed.php');

    expect(violationTuples($file))
        ->toBe([
            ['line' => 121, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ])
        ->and($file->getFixableCount())->toBe(0);
});
