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
 * Operators inside an if/elseif/while/for condition are left to
 * CleanCode.Conditionals.OneConditionPerLine, which owns condition layout (and
 * can auto-fix it) — otherwise a dangling operator inside a wrapped condition
 * would be reported by both sniffs.
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
        if ($this->isInsideControlStructureCondition($phpcsFile, $stackPtr) === true) {
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

    /**
     * Whether the operator sits directly inside the parentheses of an
     * if/elseif/while/for. Condition layout — including a dangling boolean or
     * comparison operator — is owned by CleanCode.Conditionals.OneConditionPerLine,
     * so this sniff defers to avoid double-reporting the same wrap.
     *
     * The check is the operator's innermost enclosing parenthesis: a boolean
     * nested in an inner grouping paren (`if (($a &&` …) has no control-structure
     * owner, so it is still this sniff's to report. (A for-loop's init/increment
     * sections share the same parentheses as its condition; a dangling operator
     * there — vanishingly rare — is deferred too.)
     */
    private function isInsideControlStructureCondition(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $innermostOpener = max(array_keys($tokens[$stackPtr]['nested_parenthesis']));

        if (isset($tokens[$innermostOpener]['parenthesis_owner']) === false) {
            return false;
        }

        $ownerCode = $tokens[$tokens[$innermostOpener]['parenthesis_owner']]['code'];

        return in_array($ownerCode, [T_IF, T_ELSEIF, T_WHILE, T_FOR], true);
    }
}
