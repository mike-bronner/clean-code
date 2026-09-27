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

        if (strpos($previous['content'], "\n") !== false) {
            return;
        }

        $delimiter = $tokens[$stackPtr - 2] ?? null;

        if (
            $delimiter === null
            || isset(self::OPENING_DELIMITERS[$delimiter['code']]) === false
        ) {
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
