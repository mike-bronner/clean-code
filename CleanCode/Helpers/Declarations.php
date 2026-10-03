<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;

final class Declarations
{
    private const ANONYMOUS = [
        T_CLOSURE,
        T_ANON_CLASS,
    ];

    public function name(File $phpcsFile, int $stackPtr): ?string
    {
        if (in_array($phpcsFile->getTokens()[$stackPtr]['code'], self::ANONYMOUS, true) === true) {
            return null;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        return $name === '' ? null : $name;
    }
}
