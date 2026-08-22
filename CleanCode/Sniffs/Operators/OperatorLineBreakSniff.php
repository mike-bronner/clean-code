<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the operator line-break clean-code standard: when an expression
 * wraps across lines, the operator starts the continuation line rather than
 * dangling at the end of the previous one.
 *
 * ```php
 * // compliant — the operator leads the continuation line
 * $message = $greeting
 *     . $name;
 *
 * // flagged — the operator dangles at the end of the line
 * $message = $greeting .
 *     $name;
 * ```
 *
 * A binary assignment, comparison, logical, or concatenation operator that is
 * the last code token on its line is reported (arithmetic operators such as
 * `+`/`-` are out of scope). This mirrors the standard's two rules —
 * "operators to the right of the assignment operator should start a new line"
 * and "don't line break after comparison or assignment operators".
 *
 * An operator inside an if/elseif/while/for condition is deferred to
 * CleanCode.Conditionals.OneConditionPerLine *only where that sniff actually
 * back-stops it* — otherwise the same wrap would be reported twice, or worse,
 * slip through both. Support\ConditionOperatorOwnership holds that decision,
 * shared with CleanCode.Operators.ManipulationOperatorPlacement, which polices
 * the same rule over the math and bitwise operators (#59) and has to stand down
 * on identical terms.
 *
 * Where the operator belongs on the rewritten line is a layout judgement (the
 * continuation may re-indent, merge, or split differently), so violations are
 * reported rather than auto-fixed.
 */
class OperatorLineBreakSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_merge(
            array_values(Tokens::$assignmentTokens),
            array_values(Tokens::$comparisonTokens),
            array_values(Tokens::$booleanOperators),
            [T_STRING_CONCAT]
        );
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        if (ConditionOperatorOwnership::isDeferredToOneConditionPerLine($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // Skip comments too when locating the next code token: a comment
        // explaining why an expression wraps (`$a . // note` then the operand
        // on the next line) must not mask the dangling operator.
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($next === false) {
            return;
        }

        if ($tokens[$next]['line'] <= $tokens[$stackPtr]['line']) {
            return;
        }

        $phpcsFile->addError(
            'A "%s" operator must not end a line; place it at the start of the continuation line instead',
            $stackPtr,
            'OperatorAtLineEnd',
            [$tokens[$stackPtr]['content']]
        );
    }
}
