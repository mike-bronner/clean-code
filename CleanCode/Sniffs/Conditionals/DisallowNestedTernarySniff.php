<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowNestedTernarySniff implements Sniff
{
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

    public function register(): array
    {
        return [T_INLINE_THEN];
    }

    public function process(File $phpcsFile, $stackPtr): void
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

    private function segmentHasNestingTernary(File $phpcsFile, int $stackPtr, int $direction): bool
    {
        $tokens = $phpcsFile->getTokens();
        $unwrappedGrouping = false;
        $i = $stackPtr + $direction;

        while (
            $i >= 0
            && $i < $phpcsFile->numTokens
        ) {
            $code = $tokens[$i]['code'];

            if ($direction < 0) {
                if (
                    $code === T_CLOSE_PARENTHESIS
                    && isset($tokens[$i]['parenthesis_opener']) === true
                ) {
                    $i = $tokens[$i]['parenthesis_opener'] - 1;
                    continue;
                }

                if (
                    isset($tokens[$i]['bracket_opener']) === true
                    && $tokens[$i]['bracket_opener'] < $i
                ) {
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
            }

            // $direction is +1 or -1, never 0, so the two walks are disjoint and
            // read as one choice written apart.
            if ($direction > 0) {
                if (
                    $code === T_OPEN_PARENTHESIS
                    && isset($tokens[$i]['parenthesis_closer']) === true
                ) {
                    $i = $tokens[$i]['parenthesis_closer'] + 1;
                    continue;
                }

                if (
                    isset($tokens[$i]['bracket_closer']) === true
                    && $tokens[$i]['bracket_closer'] > $i
                ) {
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
