<?php

declare(strict_types=1);

it('reports every operator violation exactly once', function (): void {
    $file = analyzeWithMasterRuleset(__DIR__ . '/fixtures/operator-rules.php');

    expect(allViolationSourcesByLine($file))->toBe([
        7 => [
            'CleanCode.Naming.ShortVariable.TooShort',
            'CleanCode.Naming.ShortVariable.TooShort',
            'Squiz.PHP.DisallowMultipleAssignments.Found',
            'Squiz.PHP.DisallowMultipleAssignments.Found',
            'Squiz.PHP.DisallowMultipleAssignments.Found',
            'Squiz.PHP.DisallowMultipleAssignments.Found',
            'Squiz.PHP.DisallowMultipleAssignments.Found',
        ],
        9 => [
            'CleanCode.Naming.DisallowMagicNumbers.Found',
            'CleanCode.Operators.BinaryOperatorSpacing.NoSpaceAfter',
            'CleanCode.Operators.BinaryOperatorSpacing.NoSpaceBefore',
        ],
        10 => [
            'CleanCode.Strings.RequireStringInterpolation.Concatenation',
            'Squiz.Strings.ConcatenationSpacing.PaddingFound',
        ],
        11 => [
            'CleanCode.Naming.DisallowMagicNumbers.Found',
            'CleanCode.Operators.BinaryOperatorSpacing.SpacingBefore',
        ],
        13 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
        16 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement'],
        17 => ['CleanCode.Conditionals.OneConditionPerLine.BooleanOperatorNotLeading'],
        20 => ['CleanCode.Naming.ShortVariable.TooShort'],
        23 => [
            'CleanCode.Conditionals.AvoidConditionals.IfStatement',
            'PSR12.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace',
        ],
        27 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement'],
        28 => [
            'CleanCode.Operators.DisallowNewlineAroundEvaluativeOperators.FoundAfter',
            'CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd',
        ],
        35 => ['CleanCode.Operators.ManipulationOperatorPlacement.OperatorNotLeading'],
        38 => [
            'CleanCode.Conditionals.AvoidConditionals.IfStatement',
            'CleanCode.Conditionals.OneConditionPerLine.SingleConditionNotOnOneLine',
        ],
    ]);
});
