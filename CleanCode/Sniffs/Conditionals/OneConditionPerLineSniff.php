<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Conditionals: One Condition Per Line" standard.
 *
 * A condition of an if/elseif/while/for/do-while with a single top-level
 * boolean expression must stay on one line with its keyword (for a for-loop,
 * the condition section must not span multiple lines). A condition combining
 * multiple top-level boolean expressions must place each one on its own line
 * with the logical operator leading the continuation line, never trailing
 * the previous one.
 *
 * Boolean operators nested inside parentheses, square brackets, or braces
 * are not top-level, so grouped sub-conditions and function-call arguments
 * count as part of a single condition. Ternary expressions are out of scope
 * (covered by the ternary-conditionals standard).
 *
 * All violations are auto-fixable except a split single condition containing
 * a comment, which joining would corrupt.
 */
class OneConditionPerLineSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE, T_FOR];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]['parenthesis_opener']) === false
            || isset($tokens[$stackPtr]['parenthesis_closer']) === false
        ) {
            return;
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        if ($tokens[$stackPtr]['code'] === T_FOR) {
            $semicolons = $this->findTopLevelTokens($phpcsFile, ($opener + 1), ($closer - 1), [T_SEMICOLON]);

            if (count($semicolons) !== 2) {
                return;
            }

            [$boundaryStart, $boundaryEnd] = $semicolons;
        } else {
            $boundaryStart = $opener;
            $boundaryEnd = $closer;
        }

        $regionStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($boundaryStart + 1), $boundaryEnd, true);

        if ($regionStart === false) {
            return;
        }

        $regionEnd = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($boundaryEnd - 1), $boundaryStart, true);

        $operators = $this->findTopLevelTokens(
            $phpcsFile,
            $regionStart,
            $regionEnd,
            array_keys(Tokens::$booleanOperators)
        );

        if ($operators === []) {
            $this->processSingleCondition(
                $phpcsFile,
                $stackPtr,
                $boundaryStart,
                $boundaryEnd,
                $regionStart,
                $regionEnd
            );

            return;
        }

        $this->processMultiCondition($phpcsFile, $stackPtr, $regionStart, $regionEnd, $operators);
    }

    /**
     * A single condition must occupy exactly one line, and — except in a
     * for-loop header, whose sections legitimately wrap — that line must be
     * the keyword's own.
     */
    private function processSingleCondition(
        File $phpcsFile,
        int $stackPtr,
        int $boundaryStart,
        int $boundaryEnd,
        int $regionStart,
        int $regionEnd
    ): void {
        $tokens = $phpcsFile->getTokens();

        $isSplit = $tokens[$regionStart]['line'] !== $tokens[$regionEnd]['line'];
        $offKeywordLine = $tokens[$stackPtr]['code'] !== T_FOR
            && $tokens[$regionStart]['line'] !== $tokens[$stackPtr]['line'];

        if ($isSplit === false && $offKeywordLine === false) {
            return;
        }

        $error = 'A single condition must stay on one line with its "%s" keyword';
        $code = 'SingleConditionNotOnOneLine';
        $data = [strtolower($tokens[$stackPtr]['content'])];

        $hasComment = $phpcsFile->findNext(Tokens::$commentTokens, ($boundaryStart + 1), $boundaryEnd) !== false;

        if ($hasComment === true) {
            $phpcsFile->addError($error, $stackPtr, $code, $data);

            return;
        }

        $fix = $phpcsFile->addFixableError($error, $stackPtr, $code, $data);

        if ($fix === false) {
            return;
        }

        $joined = $this->joinedCondition($phpcsFile, $regionStart, $regionEnd);

        if ($tokens[$stackPtr]['code'] === T_FOR) {
            $joined = ' ' . $joined;
        }

        $phpcsFile->fixer->beginChangeset();

        for ($i = ($boundaryStart + 1); $i < $boundaryEnd; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->addContent($boundaryStart, $joined);
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Multiple conditions must each occupy their own line, with the boolean
     * operator leading the continuation line rather than trailing the
     * previous one.
     *
     * @param array<int> $operators
     */
    private function processMultiCondition(
        File $phpcsFile,
        int $stackPtr,
        int $regionStart,
        int $regionEnd,
        array $operators
    ): void {
        $tokens = $phpcsFile->getTokens();
        $indent = $this->lineIndent($phpcsFile, $stackPtr) . '    ';

        if ($tokens[$regionStart]['line'] === $tokens[$regionEnd]['line']) {
            $fix = $phpcsFile->addFixableError(
                'Each condition of a multi-condition "%s" must be on its own line',
                $stackPtr,
                'MultipleConditionsOnOneLine',
                [strtolower($tokens[$stackPtr]['content'])]
            );

            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                foreach ($operators as $operator) {
                    $this->moveOperatorToOwnLine($phpcsFile, $operator, $indent);
                }

                $phpcsFile->fixer->endChangeset();
            }

            return;
        }

        foreach ($operators as $operator) {
            $previous = $phpcsFile->findPrevious(T_WHITESPACE, ($operator - 1), null, true);

            if ($tokens[$previous]['line'] !== $tokens[$operator]['line']) {
                continue;
            }

            $fix = $phpcsFile->addFixableError(
                'Boolean operator "%s" must lead its condition line, not trail the previous one',
                $operator,
                'BooleanOperatorNotLeading',
                [$tokens[$operator]['content']]
            );

            if ($fix === false) {
                continue;
            }

            $phpcsFile->fixer->beginChangeset();
            $this->moveOperatorToOwnLine($phpcsFile, $operator, $indent);
            $phpcsFile->fixer->endChangeset();
        }
    }

    /**
     * Collects pointers to the given token codes between $start and $end,
     * skipping everything nested inside parentheses, square brackets, or
     * curly braces — those belong to sub-expressions, not the top level of
     * the condition.
     *
     * @param array<int|string> $codes
     *
     * @return array<int>
     */
    private function findTopLevelTokens(File $phpcsFile, int $start, int $end, array $codes): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];

                continue;
            }

            if (
                in_array($tokens[$i]['code'], [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET], true) === true
                && isset($tokens[$i]['bracket_closer']) === true
            ) {
                $i = $tokens[$i]['bracket_closer'];

                continue;
            }

            if (
                $tokens[$i]['code'] === T_OPEN_CURLY_BRACKET
                && isset($tokens[$i]['bracket_closer']) === true
            ) {
                $i = $tokens[$i]['bracket_closer'];

                continue;
            }

            if (in_array($tokens[$i]['code'], $codes, true) === true) {
                $pointers[] = $i;
            }
        }

        return $pointers;
    }

    /**
     * Renders the condition between $start and $end as a single line,
     * collapsing whitespace runs to one space — except directly after an
     * opening parenthesis and directly before a closing parenthesis, comma,
     * or semicolon, where the join leaves no space.
     */
    private function joinedCondition(File $phpcsFile, int $start, int $end): string
    {
        $tokens = $phpcsFile->getTokens();
        $joined = '';
        $pendingSpace = false;

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] === T_WHITESPACE) {
                $pendingSpace = true;

                continue;
            }

            if (
                $pendingSpace === true
                && substr($joined, -1) !== '('
                && in_array($tokens[$i]['code'], [T_CLOSE_PARENTHESIS, T_COMMA, T_SEMICOLON], true) === false
            ) {
                $joined .= ' ';
            }

            $joined .= $tokens[$i]['content'];
            $pendingSpace = false;
        }

        return $joined;
    }

    /**
     * Rewrites the tokens around a boolean operator so it starts its own
     * line at the given indentation, with a single space separating it from
     * the operand that follows.
     */
    private function moveOperatorToOwnLine(File $phpcsFile, int $operator, string $indent): void
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = ($operator - 1); $tokens[$i]['code'] === T_WHITESPACE; $i--) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $hadTrailingWhitespace = false;

        for ($i = ($operator + 1); $tokens[$i]['code'] === T_WHITESPACE; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
            $hadTrailingWhitespace = true;
        }

        $phpcsFile->fixer->addContentBefore($operator, $phpcsFile->eolChar . $indent);

        if ($hadTrailingWhitespace === true) {
            $phpcsFile->fixer->addContent($operator, ' ');
        }
    }

    /**
     * The leading whitespace of the line the given token starts on — the
     * base indentation continuation lines build from.
     */
    private function lineIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $first = $stackPtr;

        while ($first > 0 && $tokens[$first - 1]['line'] === $tokens[$stackPtr]['line']) {
            $first--;
        }

        if ($tokens[$first]['code'] !== T_WHITESPACE) {
            return '';
        }

        return str_replace(["\r", "\n"], '', $tokens[$first]['content']);
    }
}
