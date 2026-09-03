<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowElseSniff implements Sniff
{
    private const TERMINATING_STATEMENTS = [
        T_RETURN,
        T_THROW,
        T_CONTINUE,
        T_BREAK,
        T_EXIT,
    ];

    public function register(): array
    {
        return [T_ELSE, T_ELSEIF];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // The `!== false` honours findNext()'s int|false contract. It is not a
        // behavioural branch and carries no fixture: reaching it would need a
        // file whose very last token is `else`, which is not parseable PHP.
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
        $message = 'Elseif clauses are unnecessary; use a separate if with an early exit instead';

        if ($this->isFixableElseIf($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError($message, $stackPtr, 'ElseIfFound');

            return;
        }

        if ($phpcsFile->addFixableError($message, $stackPtr, 'ElseIfFound') === true) {
            $this->fixElseIf($phpcsFile, $stackPtr);
        }
    }

    private function processElse(File $phpcsFile, int $stackPtr): void
    {
        $message = 'Else clauses are unnecessary; use an early exit or a guard clause instead';

        if ($this->isFixableElse($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError($message, $stackPtr, 'Found');

            return;
        }

        if ($phpcsFile->addFixableError($message, $stackPtr, 'Found') === true) {
            $this->fixElse($phpcsFile, $stackPtr);
        }
    }

    private function isFixableElseIf(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$stackPtr]['code'] === T_ELSEIF
            && $this->hasCurlyScope($phpcsFile, $stackPtr) === false
        ) {
            return false;
        }

        if ($this->hasCanonicalLayout($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($tokens[$stackPtr]['code'] === T_ELSE) {
            $if = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

            if (
                $if === false
                || $this->containsOnlyWhitespace($phpcsFile, ($stackPtr + 1), $if) === false
            ) {
                return false;
            }
        }

        return $this->precedingBranchesTerminate($phpcsFile, $stackPtr);
    }

    private function isFixableElse(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->hasCurlyScope($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->containsOnlyWhitespace($phpcsFile, ($stackPtr + 1), $tokens[$stackPtr]['scope_opener']) === false) {
            return false;
        }

        if ($this->hasCanonicalLayout($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->bodyStartsOnItsOwnLine($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->closesOnOwnLine($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->precedingBranchesTerminate($phpcsFile, $stackPtr);
    }

    private function hasCanonicalLayout(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $previousCloser === false
            || $tokens[$previousCloser]['line'] !== $tokens[$stackPtr]['line']
        ) {
            return false;
        }

        if ($this->containsOnlyWhitespace($phpcsFile, ($previousCloser + 1), $stackPtr) === false) {
            return false;
        }

        return $phpcsFile->findFirstOnLine(T_WHITESPACE, $previousCloser, true) === $previousCloser;
    }

    private function bodyStartsOnItsOwnLine(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $firstOfBody = $phpcsFile->findNext(T_WHITESPACE, ($scopeOpener + 1), null, true);

        return $firstOfBody === false
            || $tokens[$firstOfBody]['line'] !== $tokens[$scopeOpener]['line'];
    }

    private function closesOnOwnLine(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];

        if ($phpcsFile->findFirstOnLine(T_WHITESPACE, $scopeCloser, true) !== $scopeCloser) {
            return false;
        }

        $nextAfterCloser = $phpcsFile->findNext(T_WHITESPACE, ($scopeCloser + 1), null, true);

        return $nextAfterCloser === false
            || $tokens[$nextAfterCloser]['line'] !== $tokens[$scopeCloser]['line'];
    }

    private function containsOnlyWhitespace(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr < $end; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_WHITESPACE) {
                return false;
            }
        }

        return true;
    }

    private function hasCurlyScope(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return false;
        }

        return $tokens[$tokens[$stackPtr]['scope_opener']]['code'] === T_OPEN_CURLY_BRACKET;
    }

    private function precedingBranchesTerminate(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr;

        while (true) {
            $closer = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if (
                $closer === false
                || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET
            ) {
                return false;
            }

            if ($this->branchTerminates($phpcsFile, $closer) === false) {
                return false;
            }

            if (isset($tokens[$closer]['scope_condition']) === false) {
                return false;
            }

            $condition = $tokens[$closer]['scope_condition'];

            if ($tokens[$condition]['code'] === T_ELSEIF) {
                $ptr = $condition;

                continue;
            }

            if ($tokens[$condition]['code'] !== T_IF) {
                return false;
            }

            // A chain head, unless this `if` is the second word of an
            // `else if` — in which case the walk carries on from the `else`.
            $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($condition - 1), null, true);

            if (
                $before === false
                || $tokens[$before]['code'] !== T_ELSE
            ) {
                return true;
            }

            $ptr = $before;
        }
    }

    private function branchTerminates(File $phpcsFile, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $lastSemicolon = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($closer - 1), null, true);

        if (
            $lastSemicolon === false
            || $tokens[$lastSemicolon]['code'] !== T_SEMICOLON
        ) {
            return false;
        }

        $lastExpressionToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($lastSemicolon - 1), null, true);

        if ($lastExpressionToken === false) {
            return false;
        }

        $statementStart = $phpcsFile->findStartOfStatement($lastExpressionToken);

        return in_array($tokens[$statementStart]['code'], self::TERMINATING_STATEMENTS, true);
    }

    private function fixElseIf(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $indent = str_repeat(' ', ($tokens[$previousCloser]['column'] - 1));

        $phpcsFile->fixer
            ->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr < $stackPtr; $ptr++) {
            $phpcsFile->fixer
                ->replaceToken($ptr, '');
        }

        $phpcsFile->fixer
            ->addContent($previousCloser, "\n{$indent}");

        if ($tokens[$stackPtr]['code'] === T_ELSEIF) {
            // The keyword is the six letters of `elseif` — PHP allows no
            // whitespace inside it, that shape is the two-word form below — so
            // its last two characters are the `if` the rewrite keeps. Taking
            // them from the source rather than writing the literal `if` is
            // what keeps `ELSEIF` from coming back as lowercase. The two-word
            // form needs no equivalent: it deletes only the `else`, leaving
            // the original `if` token untouched.
            $phpcsFile->fixer
                ->replaceToken($stackPtr, substr($tokens[$stackPtr]['content'], -2));
        }

        if ($tokens[$stackPtr]['code'] === T_ELSE) {
            $if = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

            for ($ptr = $stackPtr; $ptr < $if; $ptr++) {
                $phpcsFile->fixer
                    ->replaceToken($ptr, '');
            }
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function fixElse(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];

        $phpcsFile->fixer
            ->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr <= $scopeOpener; $ptr++) {
            $phpcsFile->fixer
                ->replaceToken($ptr, '');
        }

        for ($ptr = ($scopeOpener + 1); $ptr < $scopeCloser; $ptr++) {
            if (
                $tokens[$ptr]['code'] !== T_WHITESPACE
                || $tokens[$ptr]['column'] !== 1
            ) {
                continue;
            }

            if ($tokens[$ptr]['line'] === $tokens[$scopeCloser]['line']) {
                $phpcsFile->fixer
                    ->replaceToken($ptr, '');

                continue;
            }

            $indent = $tokens[$ptr]['content'];

            // A failed read cast to a string is '', which would delete the
            // line's whole indentation instead of one level of it and leave
            // the fixed file misindented. `/^    /` is four literal spaces
            // anchored at the start — no quantifier to backtrack over, no
            // recursion, no `/u` — so nothing is known to reach this fallback;
            // it keeps the line as written if anything ever does.
            $phpcsFile->fixer
                ->replaceToken($ptr, preg_replace('/^    /', '', $indent) ?? $indent);
        }

        $phpcsFile->fixer
            ->replaceToken($scopeCloser, '');

        $afterCloser = ($scopeCloser + 1);

        if (
            isset($tokens[$afterCloser]) === true
            && $tokens[$afterCloser]['code'] === T_WHITESPACE
            && $tokens[$afterCloser]['line'] === $tokens[$scopeCloser]['line']
        ) {
            $phpcsFile->fixer
                ->replaceToken($afterCloser, '');
        }

        $phpcsFile->fixer
            ->endChangeset();
    }
}
