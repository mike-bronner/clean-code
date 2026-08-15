<?php

/**
 * End-to-end integration test for the "Arrays: Operator spacing & line breaks"
 * standard (#35). Unlike the per-sniff tests in tests/Rules and tests/Standards
 * — which narrow $ruleset->sniffs to one sniff before processing — this runs
 * the *entire* master rules.xml against one fixture and asserts every real
 * violation is reported exactly once.
 *
 * That whole-ruleset view is where operator diagnostics can duplicate:
 *   - the stricter CleanCode.Operators.BinaryOperatorSpacing stacking on the "at least
 *     one space" PSR12.Operators.OperatorSpacing (excluded in rules.xml), and
 *   - CleanCode.Operators.OperatorLineBreak overlapping
 *     CleanCode.Conditionals.OneConditionPerLine on an operator dangling inside
 *     a wrapped condition (OperatorLineBreak defers there), and
 *   - CleanCode.Operators.ManipulationOperatorPlacement (#59) overlapping
 *     OperatorLineBreak, which enforces the identical "an operator must lead
 *     the continuation line" rule over a disjoint half of the operator list.
 * A per-sniff test structurally cannot catch any of them — only this one can.
 */

declare(strict_types=1);

/**
 * The fixture holds one instance of each operator concern; every line below
 * carries exactly one diagnostic from exactly one sniff — except line 28,
 * which pins a deliberate overlap: the newline after a dangling "===" breaks
 * two distinct standards at once (#35 operator line breaks, #56 newlines
 * around evaluative operators), so both sniffs speak there. Regressions this
 * pins by adding an unplanned second (or dropping a pinned) source:
 *   - re-stacking PSR12 on Squiz spacing (line 9/10),
 *   - re-doubling OperatorLineBreak on OneConditionPerLine's boolean (17),
 *   - re-adding "(" to NotOperatorSpacing so PSR12's ControlStructureSpacing
 *     double-reports "if ( ! " (line 23), and
 *   - re-broadening OperatorLineBreak's deferral so a dangling non-boolean
 *     operator inside a multi-condition slips through unreported (line 28), and
 *   - re-registering the math or bitwise group on both line-break sniffs at
 *     once, doubling every wrapped math operator (line 35), and
 *   - narrowing ManipulationOperatorPlacement's deferral so a wrapped math
 *     operator inside a single condition is reported by it *and* collapsed by
 *     OneConditionPerLine (line 38/39).
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
            'CleanCode.Operators.BinaryOperatorSpacing.NoSpaceAfter',
            'CleanCode.Operators.BinaryOperatorSpacing.NoSpaceBefore',
        ],
        // concatenation spacing — ConcatenationSpacing only, no PSR12
        // `$joined = $a.'b';` — the concatenation-spacing rule this fixture
        // exists for, plus the Strings standard's own interpolation rule (#25),
        // which reads the same line as a literal-and-variable concatenation
        // that should be written "{$a}b". Two standards, two different
        // complaints about one expression, so both belong here.
        10 => [
            'CleanCode.Strings.RequireStringInterpolation.Concatenation',
            'Squiz.Strings.ConcatenationSpacing.PaddingFound',
        ],
        // padding before "=" — the ignoreSpacingBeforeAssignments knob, plus
        // #136 on the "3" that line assigns
        11 => [
            'CleanCode.Naming.DisallowMagicNumbers.Found',
            'CleanCode.Operators.BinaryOperatorSpacing.SpacingBefore',
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
        // dangling "===" inside a multi-condition — a deliberate overlap.
        // OperatorLineBreak (#35) reports the dangling operator
        // (OneConditionPerLine polices only the boolean "||"), and
        // DisallowNewlineAroundEvaluativeOperators (#56) reports the newline
        // after an evaluative operator. Its auto-fix — joining both operands
        // onto one line — satisfies both standards at once.
        28 => [
            'CleanCode.Operators.DisallowNewlineAroundEvaluativeOperators.FoundAfter',
            'CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd',
        ],
        // dangling "-" outside a condition — ManipulationOperatorPlacement's
        // to own (#59), and only its. OperatorLineBreak enforces the identical
        // rule over the assignment/comparison/logical/concatenation operators,
        // so the two register disjoint token sets; were the math or bitwise
        // group added back to either one, this line would carry two sources.
        35 => ['CleanCode.Operators.ManipulationOperatorPlacement.OperatorNotLeading'],
        // A dangling "+" inside a *single* condition — one carrying no
        // top-level boolean. OneConditionPerLine collapses that whole condition
        // onto one line, so ManipulationOperatorPlacement stands down and only
        // the collapse is reported, on the "if" line. This is the one deferral
        // no per-sniff test can pin: narrowing the ruleset to one sniff proves
        // only that this sniff is silent, never that the other one speaks. Were
        // the deferral dropped, line 39 would gain a second, duplicate source.
        38 => [
            'CleanCode.Conditionals.AvoidConditionals.IfStatement',
            'CleanCode.Conditionals.OneConditionPerLine.SingleConditionNotOnOneLine',
        ],
    ]);
});
