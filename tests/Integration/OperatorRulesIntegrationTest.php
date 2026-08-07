<?php

/**
 * End-to-end integration test for the "Arrays: Operator spacing & line breaks"
 * standard (#35). Unlike the per-sniff tests in tests/Rules and tests/Standards
 * — which narrow $ruleset->sniffs to one sniff before processing — this runs
 * the *entire* master rules.xml against one fixture and asserts every real
 * violation is reported exactly once.
 *
 * That whole-ruleset view is where operator diagnostics can duplicate:
 *   - the stricter Squiz.WhiteSpace.OperatorSpacing stacking on the "at least
 *     one space" PSR12.Operators.OperatorSpacing (excluded in rules.xml), and
 *   - CleanCode.Operators.OperatorLineBreak overlapping
 *     CleanCode.Conditionals.OneConditionPerLine on an operator dangling inside
 *     a wrapped condition (OperatorLineBreak defers there).
 * A per-sniff test structurally cannot catch either — only this one can.
 */

declare(strict_types=1);

/**
 * The fixture holds one instance of each operator concern; every line below
 * carries exactly one diagnostic from exactly one sniff. Regressions this
 * pins by adding a second (or dropping the only) source:
 *   - re-stacking PSR12 on Squiz spacing (line 5/6),
 *   - re-doubling OperatorLineBreak on OneConditionPerLine's boolean (13),
 *   - re-adding "(" to NotOperatorSpacing so PSR12's ControlStructureSpacing
 *     double-reports "if ( ! " (line 19), and
 *   - re-broadening OperatorLineBreak's deferral so a dangling non-boolean
 *     operator inside a multi-condition slips through unreported (line 24).
 */
it('reports every operator violation exactly once', function (): void {
    $file = analyzeWithMasterRuleset(__DIR__ . '/fixtures/operator-rules.php');

    expect(allViolationSourcesByLine($file))->toBe([
        // exactly-1-space spacing — Squiz supersedes PSR12, no stacking
        5 => [
            'Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter',
            'Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore',
        ],
        // concatenation spacing — ConcatenationSpacing only, no PSR12
        6 => ['Squiz.Strings.ConcatenationSpacing.PaddingFound'],
        // padding before "=" — the ignoreSpacingBeforeAssignments knob
        7 => ['Squiz.WhiteSpace.OperatorSpacing.SpacingBefore'],
        // dangling "." outside a condition — OperatorLineBreak's to own
        9 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
        // dangling "||" inside the if — OneConditionPerLine only
        13 => ['CleanCode.Conditionals.OneConditionPerLine.BooleanOperatorNotLeading'],
        // "if ( ! " paren padding — PSR12 only; NotOperatorSpacing defers
        19 => ['PSR12.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace'],
        // dangling "===" inside a multi-condition — OperatorLineBreak owns it
        // (OneConditionPerLine polices only the boolean "||")
        24 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
    ]);
});
