<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class NotOperatorSpacingSniff implements Sniff
{
    private const OPENING_DELIMITERS = [
        T_OPEN_SQUARE_BRACKET => true,
        T_OPEN_SHORT_ARRAY => true,
    ];

    public function register(): array
    {
        return [T_BOOLEAN_NOT];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $this->checkSpaceBefore($phpcsFile, $stackPtr);
        $this->checkSpaceAfter($phpcsFile, $stackPtr);
    }

    private function checkSpaceBefore(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $tokens[$stackPtr - 1] ?? null;

        if (
            $previous === null
            || $previous['code'] !== T_WHITESPACE
        ) {
            return;
        }

        // A space that spans a line break is a wrapping concern, not this
        // rule's; only the same-line "( ! " case is policed here.
        if (strpos($previous['content'], "\n") !== false) {
            return;
        }

        $delimiter = $tokens[$stackPtr - 2] ?? null;

        if (
            $delimiter === null
            || isset(self::OPENING_DELIMITERS[$delimiter['code']]) === false
        ) {
            // The block matched here starts at this `return` and runs into the
            // head of the addFixableError() call below — the guard that owns
            // the `return` is itself outside the window, and so is the call's
            // last argument and its fixer branch. What is left is the reporting
            // signature PHP_CodeSniffer itself defines, written one argument
            // per line: message, $stackPtr, code. Its twin is TooMuchSpaceAfter's
            // report, whose guard tests an exact single space rather than a
            // delimiter token, and whose fix collapses the space *after* the
            // operator instead of deleting the one before it. The two carry no
            // shared knowledge — only a shared API — and the third report in
            // this file fixes with addContent(), so there is not even one
            // fixer verb to extract across the set.
            // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
            return;
        }

        $fix = $phpcsFile->addFixableError(
            "The not operator must sit flush against the preceding \"%s\"; found %s space(s)",
            $stackPtr,
            'SpaceBefore',
            [$delimiter['content'], strlen($previous['content'])]
        );

        if ($fix === true) {
            $phpcsFile->fixer
                ->replaceToken($stackPtr - 1, '');
        }
    }

    private function checkSpaceAfter(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $tokens[$stackPtr + 1] ?? null;

        if ($next === null) {
            return;
        }

        if ($next['code'] !== T_WHITESPACE) {
            $fix = $phpcsFile->addFixableError(
                'Expected 1 space after the not operator; 0 found',
                $stackPtr,
                'NoSpaceAfter'
            );

            if ($fix === true) {
                $phpcsFile->fixer
                    ->addContent($stackPtr, ' ');
            }

            return;
        }

        if ($next['content'] === ' ') {
            // The other end of the SpaceBefore match; every participating block
            // is reported at its own first line. This `return` is the
            // already-compliant exit — one space is exactly what the rule
            // wants — where the matching one above exits because the preceding
            // token is not a bracket the rule polices at all. One says "correct
            // already", the other says "not my case": opposite meanings behind
            // the same token shape, and neither report that follows can be
            // reached from the other's branch.
            // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Expected 1 space after the not operator; %s found',
            $stackPtr,
            'TooMuchSpaceAfter',
            [strlen($next['content'])]
        );

        if ($fix === true) {
            $phpcsFile->fixer
                ->replaceToken($stackPtr + 1, ' ');
        }
    }
}
