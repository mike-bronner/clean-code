<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use MikeBronner\CleanCode\Support\CyclomaticComplexity;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ExcessiveClassComplexitySniff implements Sniff
{
    public $maximum = 50;

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $maximum = (int) $this->maximum;
        $count = $this->weightedMethodCount($phpcsFile, $stackPtr);

        if (
            $count === null
            || $count < $maximum
        ) {
            return;
        }

        $phpcsFile->addError(
            'Class %s has a weighted method count of %s, at or above the '
                . 'configured maximum of %s; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)',
            $stackPtr,
            'MaximumExceeded',
            [(string) $phpcsFile->getDeclarationName($stackPtr), $count, $maximum]
        );
    }

    private function weightedMethodCount(File $phpcsFile, int $classPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;

        if ($end === null) {
            return null;
        }

        $count = 0;
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $end)) !== false) {
            if ($this->isDeclaredDirectlyIn($tokens, $ptr, $classPtr) === true) {
                $count += (new CyclomaticComplexity())->forDeclaration($phpcsFile, $ptr);
            }
        }

        return $count;
    }

    private function isDeclaredDirectlyIn(array $tokens, int $functionPtr, int $classPtr): bool
    {
        return array_key_last($tokens[$functionPtr]['conditions'] ?? []) === $classPtr;
    }
}
