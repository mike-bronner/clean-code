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

        // Named functions and methods both open a T_FUNCTION scope, and a
        // closure or arrow function nested inside one still carries it among
        // its conditions. A closure at file scope carries only T_CLOSURE, so
        // it is left alone — as PHPMD leaves it alone.
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
