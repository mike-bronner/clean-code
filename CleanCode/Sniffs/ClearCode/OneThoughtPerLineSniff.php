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

    public function register(): array
    {
        return self::ACCESS_OPERATORS;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $previousOperator = $this->previousOperatorInChain($phpcsFile, $stackPtr);

        if ($previousOperator === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$previousOperator]['line'] !== $tokens[$stackPtr]['line']) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            "Each line must express a single thought: at most one \"->\", \"?->\", or \"::\" access operator"
                . ' per line; split the chain onto separate lines or extract an intermediate'
                . ' variable/attribute',
            $stackPtr,
            'MultipleAccessOperators'
        );

        if ($fix === false) {
            return;
        }

        $indent = $this->chainIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer
            ->beginChangeset();

        if ($tokens[$stackPtr - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer
                ->replaceToken($stackPtr - 1, $phpcsFile->eolChar . $indent);
        } else {
            $phpcsFile->fixer
                ->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function previousOperatorInChain(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        while ($ptr !== false) {
            $code = $tokens[$ptr]['code'];

            if (
                $code === T_CLOSE_PARENTHESIS
                && isset($tokens[$ptr]['parenthesis_opener']) === true
            ) {
                $ptr = $tokens[$ptr]['parenthesis_opener'];
            } elseif (
                ($code === T_CLOSE_SQUARE_BRACKET || $code === T_CLOSE_CURLY_BRACKET)
                && isset($tokens[$ptr]['bracket_opener']) === true
            ) {
                $ptr = $tokens[$ptr]['bracket_opener'];
            } elseif (in_array($code, self::ACCESS_OPERATORS, true) === true) {
                // Reached only after jumping a dynamic `{…}` segment
                // (`->{$prop}` / `::{$prop}`), whose braces sit directly on
                // the operator: that operator is the chain's predecessor.
                return $ptr;
            } elseif (in_array($code, self::CHAIN_SEGMENT_TOKENS, true) === true) {
                $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                if (
                    $before !== false
                    && in_array($tokens[$before]['code'], self::ACCESS_OPERATORS, true) === true
                ) {
                    return $before;
                }

                return null;
            } else {
                return null;
            }

            $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);
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
