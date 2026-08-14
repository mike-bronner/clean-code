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
 * Only direct nesting is flagged: a ternary inside a call argument, an
 * array element, a match arm, or an arrow-function body is bounded by that
 * construct and stays independently readable, even when the construct itself
 * sits in another ternary's branch. No auto-fixer is provided — unfolding a
 * nested ternary requires inventing a variable or method name, which is a
 * semantic decision. See docs/standards/conditionals-ternary-conditionals.md.
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
        T_FN_ARROW,
        T_MATCH_ARROW,
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
     * another ternary expression is flagged wherever it appears; in a chain
     * only the second and subsequent operators are flagged — with or without
     * redundant grouping parentheses around the chain — so every nesting is
     * reported exactly once, at the nested operator.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $isNested = $this->segmentHasNestingTernary($phpcsFile, $stackPtr, -1) === true
            || $this->segmentHasNestingTernary($phpcsFile, $stackPtr, 1) === true;

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
     * expression segment, looking for a ternary operator that nests the one
     * at $stackPtr. Matched parenthesis/bracket pairs are jumped over;
     * grouping-only parentheses are unwrapped; call-like parentheses, array
     * brackets, and statement delimiters end the scan.
     *
     * A backward hit always nests: an earlier operator in the same segment
     * makes this one a chain tail, and one found past a grouping parenthesis
     * is the outer ternary the grouping sits in. A forward hit nests only
     * when this scan itself crossed a grouping parenthesis first — the
     * grouping is then an operand of the later ternary. A forward sibling at
     * the same level (the head of a chain) is not nesting; the nesting is
     * reported at the later operator by its own backward scan, whether or not
     * redundant grouping parentheses wrap the chain.
     */
    private function segmentHasNestingTernary(File $phpcsFile, int $stackPtr, int $direction): bool
    {
        $tokens = $phpcsFile->getTokens();
        $unwrappedGrouping = false;
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
                        $this->closesArrowFunctionBody($phpcsFile, $i, $stackPtr) === true
                        || isset($tokens[$i]['parenthesis_opener']) === false
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
                return $direction < 0 || $unwrappedGrouping === true;
            }

            $i += $direction;
        }

        return false;
    }

    /**
     * Whether the closing parenthesis at $closerPtr ends the arrow-function
     * body that holds the ternary at $stackPtr.
     *
     * The backward scan never leaves an arrow-function body — T_FN_ARROW is a
     * segment boundary, so it stops at the arrow. The forward scan has no such
     * marker to stop at: the body ends at whatever token closes it. Where that
     * token is a parenthesis, it looks exactly like a redundant grouping
     * parenthesis, so an immediately-invoked arrow function
     * ((fn ($x) => $x ? 1 : 2)($y) ? 'a' : 'b') would be unwrapped and the
     * outer ternary past it read as nesting the body's own — the one bounded
     * construct the scan does not jump over wholesale. This is the forward
     * counterpart of that boundary: the body's ternary reads on its own, so
     * its segment ends where the body does.
     *
     * The arrow function is found by its T_FN_ARROW rather than by the closing
     * parenthesis's own scope markers, because PHP_CodeSniffer ends an arrow
     * function's scope at the last token of the body expression — which is the
     * wrapping parenthesis only when the body ends in one. In
     * (fn ($x) => $x ? ($y ? 1 : 2) : 3)($z) the scope closes on the "3", so
     * reading the parenthesis would miss the body entirely.
     *
     * Candidate arrows are walked from $stackPtr back to the parenthesis being
     * left, innermost first, and the first one whose scope still covers
     * $stackPtr is the body holding it. Skipping the ones that have already
     * closed is what separates the arrow function the ternary lives in from a
     * sibling arrow function standing earlier in the same expression.
     */
    private function closesArrowFunctionBody(File $phpcsFile, int $closerPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$closerPtr]['parenthesis_opener']) === false) {
            return false;
        }

        $limit = $tokens[$closerPtr]['parenthesis_opener'] + 1;
        $arrowPtr = $stackPtr - 1;

        while (($arrowPtr = $phpcsFile->findPrevious(T_FN_ARROW, $arrowPtr, $limit)) !== false) {
            // An arrow function always carries its scope; an unmapped one is
            // treated as enclosing, so an unreadable body bounds the segment
            // rather than opening the way to a report that cannot be trusted.
            if (($tokens[$arrowPtr]['scope_closer'] ?? $stackPtr) >= $stackPtr) {
                return true;
            }

            $arrowPtr--;
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
