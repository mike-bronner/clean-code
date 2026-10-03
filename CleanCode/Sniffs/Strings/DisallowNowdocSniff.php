<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowNowdocSniff implements Sniff
{
    private const MESSAGE
        = 'Use a HEREDOC, not a NOWDOC: the quoted opening identifier makes the body inert, and a'
        . ' HEREDOC carries the same text with a backslash and a `$` escaped';

    public function register(): array
    {
        return [T_START_NOWDOC];
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint -- interface-mandated, see CONTRIBUTING.md
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $fix = $phpcsFile->addFixableError(self::MESSAGE, $stackPtr, 'NowdocFound');

        if ($fix === false) {
            return;
        }

        $this->convert($phpcsFile, $stackPtr);
    }

    private function convert(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $literal = new StringLiteral();

        $phpcsFile->fixer
            ->beginChangeset();

        $phpcsFile->fixer
            ->replaceToken($stackPtr, str_replace("'", '', $tokens[$stackPtr]['content']));

        for ($pointer = ($stackPtr + 1); $pointer < $phpcsFile->numTokens; $pointer++) {
            if ($tokens[$pointer]['code'] === T_END_NOWDOC) {
                break;
            }

            if ($tokens[$pointer]['code'] !== T_NOWDOC) {
                continue;
            }

            $phpcsFile->fixer
                ->replaceToken($pointer, $literal->asHeredocBody($tokens[$pointer]['content']));
        }

        $phpcsFile->fixer
            ->endChangeset();
    }
}
