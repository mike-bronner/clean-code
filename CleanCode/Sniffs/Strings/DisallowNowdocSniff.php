<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

// A multi-line string uses a HEREDOC, never a NOWDOC.
//
// The two differ only in the quotes around the opening identifier, and that one
// character changes how every later reader has to think about the body: a
// NOWDOC is inert, a HEREDOC is the form the rest of this standard's fixers
// emit and the form a template, a query or a message is normally written in.
// Carrying both means a reader checks the delimiter before trusting what the
// body says, so the package keeps one.
//
// Nothing is lost in the conversion. A HEREDOC body carries any literal text a
// NOWDOC can, with a backslash and a `$` escaped, and it needs no escape at all
// for a `"` — which is why it reads better than either quoted form.
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
    public function process(File $phpcsFile, $stackPtr): void
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

        // `<<<'TEXT'` becomes `<<<TEXT`, keeping whatever whitespace the opener
        // carried so the line ending is untouched.
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
