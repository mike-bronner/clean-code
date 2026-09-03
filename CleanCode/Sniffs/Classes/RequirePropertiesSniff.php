<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class RequirePropertiesSniff implements Sniff
{
    private const PROMOTION_MODIFIERS = [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_READONLY,
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // A class with no body scope (parse error / incomplete source) has
        // nothing to inspect.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->hasState($phpcsFile, $stackPtr)) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr) ?? 'class';

        $phpcsFile->addError(
            'Class %s encapsulates no state; it declares no property, extends no class, and uses no trait'
                . ' (add at least one property)',
            $stackPtr,
            'MissingProperty',
            [$name]
        );
    }

    private function hasState(File $phpcsFile, int $classPtr): bool
    {
        return $phpcsFile->findExtendedClassName($classPtr) !== false
            || $this->hasProperty($phpcsFile, $classPtr)
            || $this->usesTrait($phpcsFile, $classPtr);
    }

    private function hasProperty(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'];
        $closer = $tokens[$classPtr]['scope_closer'];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE) {
                continue;
            }

            // Skip variables owned by a nested scope (a method body, a nested
            // or anonymous class) — only members of this class count.
            if ($this->belongsToClass($tokens[$i]['conditions'], $classPtr) === false) {
                continue;
            }

            // A member variable sitting directly in the class body (inside no
            // parentheses) is a conventional property declaration.
            if (empty($tokens[$i]['nested_parenthesis'])) {
                return true;
            }

            // Otherwise it is a parameter; it only counts when promoted.
            if ($this->isPromotedParameter($phpcsFile, $i)) {
                return true;
            }
        }

        return false;
    }

    private function usesTrait(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$classPtr]['scope_closer'];
        $usePtr = $tokens[$classPtr]['scope_opener'];

        while (($usePtr = $phpcsFile->findNext(T_USE, ($usePtr + 1), $closer)) !== false) {
            if ($this->belongsToClass($tokens[$usePtr]['conditions'], $classPtr)) {
                return true;
            }
        }

        return false;
    }

    private function belongsToClass(array $conditions, int $classPtr): bool
    {
        return $conditions !== [] && array_key_last($conditions) === $classPtr;
    }

    private function isPromotedParameter(File $phpcsFile, int $variablePtr): bool
    {
        $boundary = $phpcsFile->findPrevious([T_COMMA, T_OPEN_PARENTHESIS], ($variablePtr - 1));

        if ($boundary === false) {
            return false;
        }

        return $phpcsFile->findNext(self::PROMOTION_MODIFIERS, ($boundary + 1), $variablePtr) !== false;
    }
}
