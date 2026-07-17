<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Clear Code: One Thought Per Line" standard.
 *
 * Each line may carry at most one access operator (`->`, `?->`, or `::`) per
 * expression chain. A chain like `$user->profile->address->city` stacks
 * several thoughts on one line; the sniff flags every chain operator that
 * follows another operator of the same chain on the same line. Chains split
 * across lines (the fluent-builder style) and lines whose operators belong to
 * separate expressions (`$this->name = $user->name`) are compliant.
 *
 * The auto-fixer moves each offending operator onto its own continuation
 * line, indented one level past the line that starts the chain — the
 * behavior-preserving half of the standard. Extracting the chain into an
 * intermediate variable or model attribute is a semantic refactor (it needs a
 * name and a statement boundary) and stays with the developer.
 */
class OneThoughtPerLineSniff implements Sniff
{
    /**
     * The access operators the standard counts.
     */
    private const ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * Tokens that can form the segment between two operators of one chain
     * (member names, variables, and class references such as static/self).
     */
    private const CHAIN_SEGMENT_TOKENS = [
        T_STRING,
        T_VARIABLE,
        T_STATIC,
        T_SELF,
        T_PARENT,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::ACCESS_OPERATORS;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
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
            'Each line must express a single thought: at most one "->", "?->", or "::" access operator'
                . ' per line; split the chain onto separate lines or extract an intermediate'
                . ' variable/attribute',
            $stackPtr,
            'MultipleAccessOperators'
        );

        if ($fix === false) {
            return;
        }

        $indent = $this->chainIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer->beginChangeset();

        if ($tokens[$stackPtr - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer->replaceToken($stackPtr - 1, $phpcsFile->eolChar . $indent);
        } else {
            $phpcsFile->fixer->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Finds the access operator that precedes $stackPtr within the same
     * expression chain, walking left over the member segment and any call
     * parentheses or index brackets. Returns null when $stackPtr is the
     * first operator of its chain.
     */
    private function previousOperatorInChain(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        while ($ptr !== false) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_CLOSE_PARENTHESIS && isset($tokens[$ptr]['parenthesis_opener']) === true) {
                $ptr = $tokens[$ptr]['parenthesis_opener'];
            } elseif (
                ($code === T_CLOSE_SQUARE_BRACKET || $code === T_CLOSE_CURLY_BRACKET)
                && isset($tokens[$ptr]['bracket_opener']) === true
            ) {
                $ptr = $tokens[$ptr]['bracket_opener'];
            } elseif (in_array($code, self::CHAIN_SEGMENT_TOKENS, true) === true) {
                $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                if ($before !== false && in_array($tokens[$before]['code'], self::ACCESS_OPERATORS, true) === true) {
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

    /**
     * Continuation indent for a chain split by the fixer: the leading
     * whitespace of the line holding the chain's first operator, plus one
     * four-space level.
     */
    private function chainIndent(File $phpcsFile, int $stackPtr): string
    {
        $first = $stackPtr;

        while (($previous = $this->previousOperatorInChain($phpcsFile, $first)) !== null) {
            $first = $previous;
        }

        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$first]['line'];
        $firstOnLine = $first;

        while ($firstOnLine > 0 && $tokens[$firstOnLine - 1]['line'] === $line) {
            $firstOnLine--;
        }

        $indent = '';

        if ($tokens[$firstOnLine]['code'] === T_WHITESPACE) {
            $indent = str_replace(["\r", "\n"], '', $tokens[$firstOnLine]['content']);
        }

        return $indent . '    ';
    }
}
