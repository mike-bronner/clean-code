<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\CodeSize;

use MikeBronner\CleanCode\Helpers\Declarations;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class TooManyMethodsSniff implements Sniff
{
    public int $maxmethods = 25;

    public string $ignorepattern = '(^(set|get|is|has|with))i';

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($this->hasUsableIgnorePattern() === false) {
            $phpcsFile->addError(
                    'The ignorepattern property is not a valid regular expression: %s. '
                        . 'No method can be excluded from the count until it is corrected',
                    $stackPtr,
                    'InvalidIgnorePattern',
                    [$this->ignorepattern]
                );

            return;
        }

        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;
        $name = (new Declarations())->name($phpcsFile, $stackPtr);

        if (
            $opener === null
            || $closer === null
            || $name === null
        ) {
            return;
        }

        $counted = $this->countMethods($phpcsFile, $stackPtr, $opener, $closer);

        if ($counted <= $this->maxmethods) {
            return;
        }

        $phpcsFile->addError(
                'Class %s declares %s counted methods, more than the maximum of %s. Methods '
                    . 'matching %s are not counted. Split it into smaller classes '
                    . '(see resources/boost/guidelines/pattern-solid.md)',
                $stackPtr,
                'MaxExceeded',
                [$name, $counted, $this->maxmethods, $this->ignorepattern]
            );
    }

    private function hasUsableIgnorePattern(): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($this->ignorepattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    private function countMethods(File $phpcsFile, int $classPtr, int $opener, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $count = 0;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $name = (new Declarations())->name($phpcsFile, $ptr);

            if (
                $name === null
                || preg_match($this->ignorepattern, $name) === 1
            ) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
