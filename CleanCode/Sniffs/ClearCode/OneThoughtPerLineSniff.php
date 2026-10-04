<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class OneThoughtPerLineSniff implements Sniff
{
    private const ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const CHAIN_SEGMENT_TOKENS = [
        T_STRING,
        T_VARIABLE,
        T_STATIC,
        T_SELF,
        T_PARENT,
    ];

    private const GROUP_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
    ];

    private const GROUP_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
    ];

    private const GROUP_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    public function register(): array
    {
        return self::ACCESS_OPERATORS;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isGrouped($phpcsFile, $stackPtr) === true) {
            return;
        }

        $previousOperator = $this->previousOperatorInChain($phpcsFile, $stackPtr);

        if ($previousOperator === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$previousOperator]['line'] !== $tokens[$stackPtr]['line']) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
                "Each line must express a single thought: at most one \"->\", \"?->\", or \"::\""
                    . ' access operator per line; split the chain onto separate lines or extract'
                    . ' an intermediate variable/attribute',
                $stackPtr,
                'MultipleAccessOperators'
            );

        if ($fix === false) {
            return;
        }

        $indent = $this->chainIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer
            ->beginChangeset();

        $this->breakBefore($phpcsFile, $stackPtr, $phpcsFile->eolChar . $indent);

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function breakBefore(File $phpcsFile, int $stackPtr, string $break): void
    {
        if ($phpcsFile->getTokens()[$stackPtr - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer
                ->replaceToken($stackPtr - 1, $break);

            return;
        }

        $phpcsFile->fixer
            ->addContentBefore($stackPtr, $break);
    }

    private function isGrouped(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr - 1;

        while ($ptr >= 0) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, self::GROUP_BOUNDARIES, true) === true) {
                return false;
            }

            if (in_array($code, self::GROUP_CLOSERS, true) === true) {
                $ptr = $this->groupOpener($tokens, $ptr) - 1;

                continue;
            }

            if (
                in_array($code, self::GROUP_OPENERS, true) === true
                && $this->groupCloser($tokens, $ptr) > $stackPtr
            ) {
                return true;
            }

            $ptr--;
        }

        return false;
    }

    private function groupOpener(array $tokens, int $ptr): int
    {
        return $tokens[$ptr]['parenthesis_opener']
            ?? $tokens[$ptr]['bracket_opener']
            ?? $ptr;
    }

    private function groupCloser(array $tokens, int $ptr): int
    {
        return $tokens[$ptr]['parenthesis_closer']
            ?? $tokens[$ptr]['bracket_closer']
            ?? $ptr;
    }

    private function previousOperatorInChain(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        while ($ptr !== false) {
            $code = $tokens[$ptr]['code'];

            $opener = $this->closedBracketOpener($tokens, $ptr, $code);

            if ($opener !== null) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($opener - 1), null, true);

                continue;
            }

            if (in_array($code, self::ACCESS_OPERATORS, true) === true) {
                return $ptr;
            }

            if (in_array($code, self::CHAIN_SEGMENT_TOKENS, true) === true) {
                $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                if (
                    $before !== false
                    && in_array($tokens[$before]['code'], self::ACCESS_OPERATORS, true) === true
                ) {
                    return $before;
                }

                return null;
            }

            return null;
        }

        return null;
    }

    private function closedBracketOpener(array $tokens, int $ptr, int|string $code): ?int
    {
        if ($code === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? null;
        }

        if (
            $code === T_CLOSE_SQUARE_BRACKET
            || $code === T_CLOSE_CURLY_BRACKET
        ) {
            return $tokens[$ptr]['bracket_opener'] ?? null;
        }

        return null;
    }

    private function chainIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        $line = $tokens[$start]['line'];
        $firstOnLine = $start;

        while (
            $firstOnLine > 0
            && $tokens[$firstOnLine - 1]['line'] === $line
        ) {
            $firstOnLine--;
        }

        $indent = '';

        if ($tokens[$firstOnLine]['code'] === T_WHITESPACE) {
            $indent = str_replace(["\r", "\n"], '', $tokens[$firstOnLine]['content']);
        }

        return "{$indent}    ";
    }
}
