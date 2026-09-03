<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\Markup;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class RequireHeredocForMarkupSniff implements Sniff
{
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (Markup::containsHtmlElement($tokens[$stackPtr]['content']) === false) {
            return;
        }

        $phpcsFile->addError(
            'Embed HTML/markup in a HereDoc, not a quoted string, so it renders without quote'
                . ' escaping and reads clearly',
            $stackPtr,
            'MarkupInString'
        );
    }
}
