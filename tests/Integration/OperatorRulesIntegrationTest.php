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
 *   - re-stacking PSR12 on Squiz spacing (line 9/10),
 *   - re-doubling OperatorLineBreak on OneConditionPerLine's boolean (17),
 *   - re-adding "(" to NotOperatorSpacing so PSR12's ControlStructureSpacing
 *     double-reports "if ( ! " (line 23), and
 *   - re-broadening OperatorLineBreak's deferral so a dangling non-boolean
 *     operator inside a multi-condition slips through unreported (line 28).
 *
 * The fixture opens with a four-line preamble assigning every name it goes on
 * to use, so the master ruleset's undefined-variable rule (#85) stays quiet
 * here: this map pins operator diagnostics, and a second sniff reporting into
 * it would mask exactly the double-reporting the test exists to catch. That
 * preamble is why the pinned lines sit four below the fixture's own numbering
 * before it.
 */
it('reports every operator violation exactly once', function (): void {
    $file = analyzeWithMasterRuleset(__DIR__ . '/fixtures/operator-rules.php');

    expect(allViolationSourcesByLine($file))->toBe([
        // The preamble's own two one-letter names, reported by the master
        // ruleset's short-variable rule (#106). Listed for the same reason as
        // the DisallowMagicNumbers entries below — the map is exhaustive, and
        // that is what makes a second *operator* source here a failure.
        7 => [
            'CleanCode.Naming.ShortVariable.TooShort',
            'CleanCode.Naming.ShortVariable.TooShort',
        ],
        // exactly-1-space spacing — Squiz supersedes PSR12, no stacking. The
        // DisallowMagicNumbers entry is the "2" of `$sum = 1+2;`: the operands
        // this line uses to carry a spacing defect are numeric literals, and
        // #136 speaks about the one not on its ignore list. Listed for the
        // same reason as AvoidConditionals below — the map is exhaustive, and
        // that is what makes a second *operator* source here a failure.
        9 => [
            'CleanCode.Naming.DisallowMagicNumbers.Found',
            'Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter',
            'Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore',
        ],
        // concatenation spacing — ConcatenationSpacing only, no PSR12
        10 => ['Squiz.Strings.ConcatenationSpacing.PaddingFound'],
        // padding before "=" — the ignoreSpacingBeforeAssignments knob, plus
        // #136 on the "3" that line assigns
        11 => [
            'CleanCode.Naming.DisallowMagicNumbers.Found',
            'Squiz.WhiteSpace.OperatorSpacing.SpacingBefore',
        ],
        // dangling "." outside a condition — OperatorLineBreak's to own
        13 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
        // the three "if" keywords the operator fixtures wrap their conditions
        // in. AvoidConditionals (#12) warns once per branch across the whole
        // ruleset, so it speaks about every conditional this fixture uses to
        // set up an operator case. Listed rather than filtered out: the map is
        // exhaustive on purpose, and that is what makes a *second* operator
        // source appearing on any of these lines a failure.
        16 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement'],
        // dangling "||" inside the if — OneConditionPerLine only
        17 => ['CleanCode.Conditionals.OneConditionPerLine.BooleanOperatorNotLeading'],
        // "if ( ! " paren padding — PSR12 only; NotOperatorSpacing defers
        // `$ok`, the body of the multi-line condition on 20, is two
        // characters — the short-variable rule (#106) again, and structural
        // fixture noise for the same reason as line 7 above.
        20 => ['CleanCode.Naming.ShortVariable.TooShort'],
        23 => [
            'CleanCode.Conditionals.AvoidConditionals.IfStatement',
            'PSR12.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace',
        ],
        27 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement'],
        // dangling "===" inside a multi-condition — OperatorLineBreak owns it
        // (OneConditionPerLine polices only the boolean "||")
        28 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
    ]);
});
