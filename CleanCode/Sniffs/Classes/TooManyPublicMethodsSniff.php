<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class TooManyPublicMethodsSniff implements Sniff
{
    private const DEFAULT_MAX_METHODS = 10;

    private const DEFAULT_IGNORE_PATTERN = '(^(set|get|is|has|with))i';

    public ?int $maxmethods = self::DEFAULT_MAX_METHODS;

    public ?string $ignorepattern = self::DEFAULT_IGNORE_PATTERN;

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // A class cut short mid-edit has no brace pair to scan between.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $threshold = $this->maxmethods ?? self::DEFAULT_MAX_METHODS;
        $count = $this->countPublicMethods($phpcsFile, $stackPtr);

        if ($count <= $threshold) {
            return;
        }

        $name = (string) $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The class %s has %s public methods. Consider refactoring %s to keep the number of '
                . 'public methods under %s '
                . '(see docs/phpmd/codesize-toomanypublicmethods.md)',
            $stackPtr,
            'Found',
            [$name, $count, $name, $threshold]
        );
    }

    private function countPublicMethods(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $pointer = $tokens[$stackPtr]['scope_opener'];
        $count = 0;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $closer)) !== false) {
            if ($this->isDeclaredBy($phpcsFile, $pointer, $stackPtr) === false) {
                continue;
            }

            if ($phpcsFile->getMethodProperties($pointer)['scope'] !== 'public') {
                continue;
            }

            if ($this->isIgnoredName($phpcsFile->getDeclarationName($pointer)) === true) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    private function isDeclaredBy(File $phpcsFile, int $methodPtr, int $stackPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$methodPtr]['conditions'] ?? [];

        return array_key_last($conditions) === $stackPtr;
    }

    private function isIgnoredName(?string $name): bool
    {
        $pattern = trim((string) $this->ignorepattern);

        return $name !== null
            && $pattern !== ''
            && preg_match($pattern, $name) === 1;
    }
}
