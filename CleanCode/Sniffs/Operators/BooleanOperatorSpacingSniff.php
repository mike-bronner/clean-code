<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces exactly one space between a boolean operator and each of the two
 * operands it joins: `&&`, `||`, and the word forms `and`, `or`, `xor`.
 *
 * These five are the only members of the "Operators: Active" standard (#62)
 * that nothing else in the master ruleset already polices. The standard's
 * remaining operators are owned, one sniff each, by rules wired in for #35:
 *
 * - every assignment operator (`=`, `+=`, `??=`, `<<=`, …) —
 *   Squiz.WhiteSpace.OperatorSpacing
 * - string concatenation (`.`) — Squiz.Strings.ConcatenationSpacing
 * - logical not (`!`) — CleanCode.Operators.NotOperatorSpacing
 *
 * Registering any of those here as well would report each of their violations
 * twice. PHP_CodeSniffer's "a later rule's configuration of the *same* sniff
 * wins" merge cannot collapse diagnostics from two *different* sniffs, so the
 * split is load-bearing rather than stylistic, and is pinned by
 * tests/Integration/OperatorRulesIntegrationTest.php.
 *
 * Squiz.WhiteSpace.OperatorSpacing is extended rather than reimplemented: its
 * spacing checks and fixers are exactly the behaviour this standard wants, and
 * its own register() targets comparison, arithmetic/bitwise and assignment
 * tokens while leaving Tokens::$booleanOperators out entirely — so re-pointing
 * register() at that one group adds the missing operators without overlapping
 * anything the parent already reports. Violation codes are the parent's:
 * NoSpaceBefore, NoSpaceAfter, SpacingBefore, SpacingAfter. All are
 * auto-fixable.
 */
class BooleanOperatorSpacingSniff extends OperatorSpacingSniff
{
    /**
     * A newline beside a boolean operator is valid separation, not a spacing
     * defect: how a wrapped boolean expression breaks across lines belongs to
     * CleanCode.Operators.OperatorLineBreak and
     * CleanCode.Conditionals.OneConditionPerLine. Without this, a dangling
     * `||` inside a wrapped condition would be reported by this sniff as well
     * as by whichever of those two owns it.
     *
     * @var boolean
     */
    public $ignoreNewlines = true;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        // Called for its side effect: the parent builds its internal
        // non-operand token map here rather than in a constructor. The
        // returned target list is deliberately discarded — it is the parent's
        // own operator set, which this sniff replaces wholesale.
        //
        // Today that map changes nothing here, and the honest statement is
        // that removing this line breaks no test: the parent reads it only
        // while deciding whether a T_MINUS/T_PLUS is a unary sign, and this
        // sniff registers neither. It is kept because leaving an inherited
        // initialiser uncalled makes correctness depend on which of the
        // parent's private fields its process() happens to touch for our
        // tokens — a detail no test of ours pins and a PHP_CodeSniffer
        // upgrade may change.
        parent::register();

        return Tokens::$booleanOperators;
    }
}
