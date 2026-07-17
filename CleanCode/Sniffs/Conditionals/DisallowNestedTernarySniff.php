<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids nesting a ternary expression inside another ternary expression.
 *
 * Enforces the nesting half of the "Conditionals: Ternary Conditionals"
 * clean-code standard: ternaries must stay single-level — assign intermediate
 * results to variables or refactor to methods instead of nesting. Both
 * parenthesized nesting ($a ? ($b ? 1 : 2) : 3, in any of the three operand
 * positions) and short-ternary chains ($a ?: $b ?: $c) are flagged, at the
 * inner (nested) operator.
 *
 * Only direct nesting is flagged: a ternary inside a call argument or an
 * array element is bounded by that construct and stays independently
 * readable, even when the call or array itself sits in another ternary's
 * branch. No auto-fixer is provided — unfolding a nested ternary requires
 * inventing a variable or method name, which is a semantic decision. See
 * docs/standards/conditionals-ternary-conditionals.md.
 */
class DisallowNestedTernarySniff implements Sniff
{
    /**
     * Tokens that end the expression segment a ternary lives in, in either
     * scan direction. Matched parenthesis/bracket pairs are jumped over
     * before this list applies, so reaching one of these means the segment
     * boundary is real.
     */
    private const SEGMENT_BOUNDARIES = [
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_TAG,
        T_COLON,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON,
    ];

    /**
     * Tokens that, directly before an opening parenthesis, mean the
     * parenthesis belongs to a call-like construct (function call, dynamic
     * call, language construct) rather than pure expression grouping.
     */
    private const CALL_PRECEDERS = [
        T_ANON_CLASS,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_EMPTY,
        T_EVAL,
        T_EXIT,
        T_ISSET,
        T_LIST,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_NAME_RELATIVE,
        T_PARENT,
        T_SELF,
        T_STATIC,
        T_STRING,
        T_UNSET,
        T_USE,
        T_VARIABLE,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_INLINE_THEN];
    }

    /**
     * A ternary is nested when its expression segment — the region between
     * real boundaries (statement delimiters, commas, call/declaration
     * parentheses, array brackets), unwrapping parentheses that only group —
     * contains another ternary operator. A parenthesized ternary inside
     * another ternary expression is flagged wherever it appears; in an
     * unparenthesized chain only the second and subsequent operators are
     * flagged, so every nesting is reported exactly once, at the nested
     * operator.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $unwrappedGrouping = false;
        $earlierTernary = $this->segmentHasSiblingTernary($phpcsFile, $stackPtr, -1, $unwrappedGrouping);
        $laterTernary = $this->segmentHasSiblingTernary($phpcsFile, $stackPtr, 1, $unwrappedGrouping);

        $isNested = $unwrappedGrouping === true
            ? ($earlierTernary === true || $laterTernary === true)
            : $earlierTernary === true;

        if ($isNested === false) {
            return;
        }

        $phpcsFile->addError(
            'Nested ternary conditions are not allowed; assign intermediate results to variables'
                . ' or refactor to methods',
            $stackPtr,
            'NestedTernary'
        );
    }

    /**
     * Scans from the ternary operator at $stackPtr toward one end of its
     * expression segment, looking for another ternary operator at the same
     * expression level. Matched parenthesis/bracket pairs are jumped over;
     * grouping-only parentheses are unwrapped (recorded in
     * $unwrappedGrouping); call-like parentheses, array brackets, and
     * statement delimiters end the scan.
     */
    private function segmentHasSiblingTernary(
        File $phpcsFile,
        int $stackPtr,
        int $direction,
        bool &$unwrappedGrouping
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $i = $stackPtr + $direction;

        while ($i >= 0 && $i < $phpcsFile->numTokens) {
            $code = $tokens[$i]['code'];

            if ($direction < 0) {
                if ($code === T_CLOSE_PARENTHESIS && isset($tokens[$i]['parenthesis_opener']) === true) {
                    $i = $tokens[$i]['parenthesis_opener'] - 1;
                    continue;
                }

                if (isset($tokens[$i]['bracket_opener']) === true && $tokens[$i]['bracket_opener'] < $i) {
                    $i = $tokens[$i]['bracket_opener'] - 1;
                    continue;
                }

                if ($code === T_OPEN_PARENTHESIS) {
                    if ($this->isGroupingParenthesis($phpcsFile, $i) === false) {
                        break;
                    }

                    $unwrappedGrouping = true;
                    $i--;
                    continue;
                }
            } else {
                if ($code === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer']) === true) {
                    $i = $tokens[$i]['parenthesis_closer'] + 1;
                    continue;
                }

                if (isset($tokens[$i]['bracket_closer']) === true && $tokens[$i]['bracket_closer'] > $i) {
                    $i = $tokens[$i]['bracket_closer'] + 1;
                    continue;
                }

                if ($code === T_CLOSE_PARENTHESIS) {
                    if (
                        isset($tokens[$i]['parenthesis_opener']) === false
                        || $this->isGroupingParenthesis($phpcsFile, $tokens[$i]['parenthesis_opener']) === false
                    ) {
                        break;
                    }

                    $unwrappedGrouping = true;
                    $i++;
                    continue;
                }
            }

            if (in_array($code, self::SEGMENT_BOUNDARIES, true) === true) {
                break;
            }

            if ($code === T_INLINE_THEN) {
                return true;
            }

            $i += $direction;
        }

        return false;
    }

    /**
     * Whether the opening parenthesis at $openerPtr only groups an
     * expression. Parentheses owned by a construct (control structure,
     * declaration, isset/list/…) or directly preceded by a call-like token
     * bound their own context instead.
     */
    private function isGroupingParenthesis(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$openerPtr]['parenthesis_owner']) === true) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

        if ($prev === false) {
            return true;
        }

        return in_array($tokens[$prev]['code'], self::CALL_PRECEDERS, true) === false;
    }
}
