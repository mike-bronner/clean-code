<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class TooManyInterfaceMethodsSniff implements Sniff
{
    public int $maxMethods = 5;

    public function register(): array
    {
        return [T_INTERFACE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // An interface PHPCS tokenised mid-edit carries no body to count, and
        // one whose name has not been typed yet carries nothing to report it
        // under, so there is nothing to say about either yet.
        if (
            $opener === null
            || $closer === null
            || $name === null
        ) {
            return;
        }

        $declared = $this->countMethods($phpcsFile, $opener, $closer);

        if ($declared <= $this->maxMethods) {
            return;
        }

        $phpcsFile->addWarning(
            'Interface %s declares %s method signatures, more than the maximum of %s. A '
                . 'wide interface forces implementers to depend on signatures they do not '
                . 'use, so split it into narrower interfaces along the lines its clients '
                . 'actually use (Interface Segregation, see docs/standards/pattern-solid.md)',
            $stackPtr,
            'MaxExceeded',
            [$name, $declared, $this->maxMethods]
        );
    }

    private function countMethods(File $phpcsFile, int $opener, int $closer): int
    {
        $count = 0;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            ++$count;
        }

        return $count;
    }
}
