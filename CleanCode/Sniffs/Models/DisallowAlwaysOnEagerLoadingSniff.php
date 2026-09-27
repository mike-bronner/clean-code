<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowAlwaysOnEagerLoadingSniff implements Sniff
{
    public array $modelParentClasses = [
        'Authenticatable',
        'Model',
        'Pivot',
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->hasModelShapedParent($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        $end = $tokens[$stackPtr]['scope_closer'] ?? null;
        $ptr = $stackPtr;

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $end)) !== false) {
            if ($tokens[$ptr]['content'] !== '$with') {
                continue;
            }

            if (array_key_last($tokens[$ptr]['conditions']) !== $stackPtr) {
                continue;
            }

            if ((new ParameterDeclaration())->isPlainParameter($phpcsFile, $ptr) === true) {
                continue;
            }

            if ($this->hasNonEmptyArrayDefault($phpcsFile, $ptr, $end) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                'Model property $with eager loads relationships on every query, which '
                    . 'bloats the result set; load them explicitly at the query site with '
                    . 'with() instead (see docs/standards/models-eager-loading.md)',
                $ptr,
                'Found'
            );
        }
    }

    private function hasModelShapedParent(File $phpcsFile, int $classPtr): bool
    {
        $parent = $phpcsFile->findExtendedClassName($classPtr);

        if ($parent === false) {
            return false;
        }

        $qualifiers = explode('\\', $parent);
        $shortName = strtolower(end($qualifiers));

        if (in_array($shortName, array_map('strtolower', $this->modelParentClasses), true) === true) {
            return true;
        }

        return str_ends_with($shortName, 'model');
    }

    private function hasNonEmptyArrayDefault(File $phpcsFile, int $propertyPtr, ?int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $equalPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($propertyPtr + 1), $end, true);

        if (
            $equalPtr === false
            || $tokens[$equalPtr]['code'] !== T_EQUAL
        ) {
            return false;
        }

        $defaultPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($equalPtr + 1), $end, true);

        if ($defaultPtr === false) {
            return false;
        }

        $bounds = $this->arrayBounds($tokens, $defaultPtr);

        if ($bounds === null) {
            return false;
        }

        [$opener, $closer] = $bounds;

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true) !== false;
    }

    private function arrayBounds(array $tokens, int $ptr): ?array
    {
        if ($tokens[$ptr]['code'] === T_OPEN_SHORT_ARRAY) {
            return [$ptr, $tokens[$ptr]['bracket_closer']];
        }

        if (
            $tokens[$ptr]['code'] === T_ARRAY
            && isset($tokens[$ptr]['parenthesis_opener'], $tokens[$ptr]['parenthesis_closer']) === true
        ) {
            return [$tokens[$ptr]['parenthesis_opener'], $tokens[$ptr]['parenthesis_closer']];
        }

        return null;
    }
}
