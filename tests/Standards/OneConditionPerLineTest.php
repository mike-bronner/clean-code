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
        ['line' => 137, 'column' => 20, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 142, 'column' => 5, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 146, 'column' => 44, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 150, 'column' => 14, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 155, 'column' => 21, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 159, 'column' => 19, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 160, 'column' => 12, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 163, 'column' => 18, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 164, 'column' => 28, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 167, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 174, 'column' => 5, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 176, 'column' => 8, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 177, 'column' => 11, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 181, 'column' => 20, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 181, 'column' => 28, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 184, 'column' => 18, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 187, 'column' => 36, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 190, 'column' => 25, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 195, 'column' => 23, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 195, 'column' => 33, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 195, 'column' => 46, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 196, 'column' => 22, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 196, 'column' => 32, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 196, 'column' => 45, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 196, 'column' => 56, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 197, 'column' => 25, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 197, 'column' => 36, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 202, 'column' => 5, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 206, 'column' => 5, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 210, 'column' => 5, 'source' => ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine'],
        ['line' => 210, 'column' => 23, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 210, 'column' => 33, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
        ['line' => 210, 'column' => 46, 'source' => ONE_CONDITION_PER_LINE . '.TernaryOperatorNotLeading'],
        ['line' => 214, 'column' => 21, 'source' => ONE_CONDITION_PER_LINE . '.BooleanOperatorNotLeading'],
    ])->and($file->getWarnings())->toBe([]);
});

it('auto-fixes the failing fixture into the autofixed fixture', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('OneConditionPerLineSniff', 'autofixed.php')));
});

it('names the expression context in its message', function (): void {
    $errors = analyzeFixture(ONE_CONDITION_PER_LINE, 'failing.php')->getErrors();
    $messages = array_map(
            static fn (int $line): string => current(current($errors[$line]))['message'],
            [137, 142, 146, 150]
        );

    expect($messages)->toBe([
        'Each condition of a multi-condition "=" must be on its own line',
        'Each condition of a multi-condition "return" must be on its own line',
        'Each condition of a multi-condition "fn" must be on its own line',
        'Each condition of a multi-condition "match" must be on its own line',
    ]);
});

it('ends an expression at the token that closes it', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'expression-boundaries.php');
    $source = ONE_CONDITION_PER_LINE . '.MultipleConditionsOnOneLine';

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 22, 'source' => $source],
        ['line' => 6, 'column' => 18, 'source' => $source],
        ['line' => 9, 'column' => 13, 'source' => $source],
        ['line' => 13, 'column' => 13, 'source' => $source],
        ['line' => 16, 'column' => 25, 'source' => $source],
        ['line' => 19, 'column' => 17, 'source' => $source],
    ]);
});

it('leaves a line-end boolean operator outside a condition to OperatorLineBreak', function (): void {
    $file = analyzeWithSniffs(
            [ONE_CONDITION_PER_LINE, 'CleanCode.Operators.OperatorLineBreak'],
            fixturePath('OneConditionPerLineSniff', 'expression-line-end-operator.php')
        );

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 21, 'source' => 'CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
        ['line' => 6, 'column' => 29, 'source' => 'CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
    ]);
});

it('reports but does not fix a split single condition wrapping a comment', function (): void {
    $file = analyzeFixture(ONE_CONDITION_PER_LINE, 'autofixed.php');

    expect(violationTuples($file))
        ->toBe([
            ['line' => 121, 'column' => 1, 'source' => ONE_CONDITION_PER_LINE . '.SingleConditionNotOnOneLine'],
        ])
        ->and($file->getFixableCount())->toBe(0);
});
