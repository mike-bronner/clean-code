<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class EscapeNestedQuotesSniff implements Sniff
{
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        if (
            StringLiteral::isComplete($content) === false
            || StringLiteral::delimiter($content) !== "'"
        ) {
            return;
        }

        $inner = StringLiteral::inner($content);

        if (strpos($inner, "\"") === false) {
            return;
        }

        if ($this->isSafeToConvert($inner) === false) {
            $phpcsFile->addError(
                'Prefer a double-quoted string with escaped inner quotes over single quotes;'
                    . ' this literal needs manual conversion (it contains a variable, brace, or escape)',
                $stackPtr,
                'UnescapedQuote'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Use a double-quoted string with escaped inner quotes instead of switching to single'
                . ' quotes to avoid escaping',
            $stackPtr,
            'UnescapedQuote'
        );

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer
            ->replaceToken(
                $stackPtr,
                StringLiteral::prefix($content) . "\"" . str_replace("\"", '\\"', $inner) . "\""
            );
    }

    private function isSafeToConvert(string $inner): bool
    {
        return strpbrk($inner, '${\\') === false;
    }
}
