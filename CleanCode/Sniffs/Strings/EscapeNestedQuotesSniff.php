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
            (new StringLiteral())->isComplete($content) === false
            || (new StringLiteral())->delimiter($content) !== "'"
        ) {
            return;
        }

        $inner = (new StringLiteral())->inner($content);

        if (strpos($inner, "\"") === false) {
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

        $literal = new StringLiteral();
        $body = $literal->singleQuotedInnerAsDoubleQuoted($inner);

        $phpcsFile->fixer
            ->replaceToken($stackPtr, "{$literal->prefix($content)}\"{$body}\"");
    }
}
