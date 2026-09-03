<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use WeakMap;

final class TokenStreams
{
    private static ?WeakMap $identities = null;

    private static int $lastIdentity = 0;

    public static function key(File $phpcsFile): string
    {
        return self::identity($phpcsFile)
            . '|' . count($phpcsFile->getTokens())
            . '|' . ($phpcsFile->fixer
                ->loops ?? 0);
    }

    private static function identity(File $phpcsFile): int
    {
        self::$identities ??= new WeakMap();

        return self::$identities[$phpcsFile] ??= ++self::$lastIdentity;
    }
}
