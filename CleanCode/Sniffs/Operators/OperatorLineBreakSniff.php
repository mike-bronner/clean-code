<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

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
 * $total = $subtotal
 *     + $tax;
 *
 * // flagged — the operator dangles at the end of the line
 * $total = $subtotal +
 *     $tax;
 * ```
 *
 * A binary assignment, comparison, logical, or concatenation operator that is
 * the last code token on its line is reported. This mirrors the standard's two
 * rules — "operators to the right of the assignment operator should start a new
 * line" and "don't line break after comparison or assignment operators".
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
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);

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
