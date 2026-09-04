<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use WeakMap;

final class TokenStreams
{
    private ?WeakMap $identities = null;

    private int $lastIdentity = 0;

    public function key(File $phpcsFile): string
    {
        return $this->identity($phpcsFile)
            . '|' . count($phpcsFile->getTokens())
            . '|' . ($phpcsFile->fixer
                ->loops ?? 0);
    }

    private function identity(File $phpcsFile): int
    {
        $this->identities ??= new WeakMap();

        return $this->identities[$phpcsFile] ??= ++$this->lastIdentity;
    }
}
