<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowExitExpressionSniff implements Sniff
{
    public function register(): array
    {
        return [T_EXIT];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array(T_FUNCTION, $tokens[$stackPtr]['conditions'], true) === false) {
            return;
        }

        $phpcsFile->addError(
            'Exit expression %s must not appear inside a function or method; '
                . 'relocate it to a startup script that returns an error code',
            $stackPtr,
            'Found',
            [$tokens[$stackPtr]['content']]
        );
    }
}
