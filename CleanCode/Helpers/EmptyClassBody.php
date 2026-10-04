<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;

final class EmptyClassBody
{
    private const CLASS_LIKES = [
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
        T_ANON_CLASS,
    ];

    public function isInline(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $declaration = $tokens[$stackPtr];

        if (
            in_array($declaration['code'], self::CLASS_LIKES, true) === false
            || isset($declaration['scope_opener'], $declaration['scope_closer']) === false
        ) {
            return false;
        }

        $opener = $declaration['scope_opener'];
        $declarationEnd = $phpcsFile->findPrevious(T_WHITESPACE, $opener - 1, $stackPtr, true);

        return $declaration['scope_closer'] === $opener + 1
            && $tokens[$declarationEnd]['line'] === $tokens[$opener]['line'];
    }
}
