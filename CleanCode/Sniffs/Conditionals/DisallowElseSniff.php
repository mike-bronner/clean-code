<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids every use of `else` and `elseif` (including `else if`).
 *
 * Restructure with early exits (guard clauses) instead. Auto-fix is applied
 * only where it provably preserves behavior: when the branch before the
 * `else`/`elseif` ends in a terminating statement (return, throw, continue,
 * break, exit), a plain `else` wrapper is removed and its body dedented, and
 * an `elseif` becomes a standalone `if`. Anything else (non-terminating
 * branches, braceless bodies, alternative syntax) is flagged but left for a
 * manual refactor.
 */
class DisallowElseSniff implements Sniff
{
    /**
     * Statements that unconditionally leave the branch, making the
     * `else`/`elseif` wrapper redundant and its removal behavior-preserving.
     */
    private const TERMINATING_STATEMENTS = [
        T_RETURN,
        T_THROW,
        T_CONTINUE,
        T_BREAK,
        T_EXIT,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_ELSE, T_ELSEIF];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);
        $isElseIf = $tokens[$stackPtr]['code'] === T_ELSEIF
            || ($next !== false && $tokens[$next]['code'] === T_IF);

        if ($isElseIf === true) {
            $this->processElseIf($phpcsFile, $stackPtr);

            return;
        }

        $this->processElse($phpcsFile, $stackPtr);
    }

    private function processElseIf(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isFixableElseIf($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError(
                'Use of "elseif" is forbidden; use a separate "if" statement with an early exit instead',
                $stackPtr,
                'ElseIfFound'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Use of "elseif" is forbidden; use a separate "if" statement with an early exit instead',
            $stackPtr,
            'ElseIfFound'
        );

        if ($fix === false) {
            return;
        }

        $this->fixElseIf($phpcsFile, $stackPtr);
    }

    private function processElse(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isFixableElse($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError(
                'Use of "else" is forbidden; restructure with an early exit (guard clause) instead',
                $stackPtr,
                'ElseFound'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Use of "else" is forbidden; restructure with an early exit (guard clause) instead',
            $stackPtr,
            'ElseFound'
        );

        if ($fix === false) {
            return;
        }

        $this->fixElse($phpcsFile, $stackPtr);
    }

    /**
     * An `elseif` (or `else if`) is safely rewritten as a standalone `if`
     * only when the preceding branch always terminates and the construct
     * uses curly braces (alternative syntax would need its own `endif`).
     */
    private function isFixableElseIf(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_ELSEIF && $this->hasCurlyScope($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->previousBranchTerminates($phpcsFile, $stackPtr);
    }

    /**
     * A plain `else` wrapper is safely removed only when it has a curly-brace
     * body and the preceding branch always terminates.
     */
    private function isFixableElse(File $phpcsFile, int $stackPtr): bool
    {
        if ($this->hasCurlyScope($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->previousBranchTerminates($phpcsFile, $stackPtr);
    }

    private function hasCurlyScope(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return false;
        }

        return $tokens[$tokens[$stackPtr]['scope_opener']]['code'] === T_OPEN_CURLY_BRACKET;
    }

    /**
     * Whether the branch directly before the `else`/`elseif` at $stackPtr
     * ends in a statement that unconditionally leaves it, so removing the
     * wrapper cannot change runtime behavior. Anything unclear (braceless
     * branch, empty body, nested construct as last statement) returns false
     * — flagged but not auto-fixed.
     */
    private function previousBranchTerminates(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previousCloser === false || $tokens[$previousCloser]['code'] !== T_CLOSE_CURLY_BRACKET) {
            return false;
        }

        $lastSemicolon = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previousCloser - 1), null, true);

        if ($lastSemicolon === false || $tokens[$lastSemicolon]['code'] !== T_SEMICOLON) {
            return false;
        }

        $lastExpressionToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($lastSemicolon - 1), null, true);

        if ($lastExpressionToken === false) {
            return false;
        }

        $statementStart = $phpcsFile->findStartOfStatement($lastExpressionToken);

        return in_array($tokens[$statementStart]['code'], self::TERMINATING_STATEMENTS, true);
    }

    /**
     * Rewrites `} elseif (…) {` as `}\n if (…) {` (and `} else if (…)` as
     * `}\n if (…)`), aligned with the closing brace of the previous branch.
     */
    private function fixElseIf(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $indent = str_repeat(' ', ($tokens[$previousCloser]['column'] - 1));

        $phpcsFile->fixer->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr < $stackPtr; $ptr++) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }

        $phpcsFile->fixer->addContent($previousCloser, "\n" . $indent);

        if ($tokens[$stackPtr]['code'] === T_ELSEIF) {
            $phpcsFile->fixer->replaceToken($stackPtr, 'if');
        }

        if ($tokens[$stackPtr]['code'] === T_ELSE) {
            $if = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

            for ($ptr = $stackPtr; $ptr < $if; $ptr++) {
                $phpcsFile->fixer->replaceToken($ptr, '');
            }
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Removes the `else` wrapper entirely: ` else {` and the closing `}` are
     * deleted and every body line is dedented one level (four spaces).
     * PHPCS splits whitespace tokens at newlines, so a line's indent is a
     * standalone column-1 T_WHITESPACE token.
     */
    private function fixElse(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];

        $phpcsFile->fixer->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr <= $scopeOpener; $ptr++) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }

        for ($ptr = ($scopeOpener + 1); $ptr < $scopeCloser; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_WHITESPACE || $tokens[$ptr]['column'] !== 1) {
                continue;
            }

            if ($tokens[$ptr]['line'] === $tokens[$scopeCloser]['line']) {
                $phpcsFile->fixer->replaceToken($ptr, '');

                continue;
            }

            $phpcsFile->fixer->replaceToken(
                $ptr,
                (string) preg_replace('/^    /', '', $tokens[$ptr]['content'])
            );
        }

        $phpcsFile->fixer->replaceToken($scopeCloser, '');

        $afterCloser = ($scopeCloser + 1);

        if (
            isset($tokens[$afterCloser]) === true
            && $tokens[$afterCloser]['code'] === T_WHITESPACE
            && $tokens[$afterCloser]['line'] === $tokens[$scopeCloser]['line']
        ) {
            $phpcsFile->fixer->replaceToken($afterCloser, '');
        }

        $phpcsFile->fixer->endChangeset();
    }
}
