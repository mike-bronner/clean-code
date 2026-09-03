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

        // $end bounds the walk; it is not what scopes it. The conditions check
        // below is the scoping rule, and it independently rejects everything a
        // file-wide walk would additionally reach — a later class's or trait's
        // own property answers to that class or trait, and PHP has no nested
        // classes. Setting $end to null therefore changes no result, only the
        // work done, which is why no fixture pins it. This differs from the
        // sibling RequireLazyLoadingPrevention sniff, whose equivalent bound is
        // its only scoping and is pinned by a fixture.
        $end = $tokens[$stackPtr]['scope_closer'] ?? null;
        $ptr = $stackPtr;

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $end)) !== false) {
            if ($tokens[$ptr]['content'] !== '$with') {
                continue;
            }

            // PHPCS records 'conditions' on every token, so the innermost
            // enclosing scope is always readable. Anything but this class means
            // a local variable in a method or a nested class's own property.
            if (array_key_last($tokens[$ptr]['conditions']) !== $stackPtr) {
                continue;
            }

            // Promotion is the exception a bare conditions check cannot see:
            // `__construct(public array $with = ['author'])` declares the
            // property and its default, so it eager loads exactly like the long
            // form, while an ordinary `$with` parameter declares nothing. See
            // ParameterDeclaration for why the two are indistinguishable by
            // position.
            if (ParameterDeclaration::isPlainParameter($phpcsFile, $ptr) === true) {
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

        // explode() always yields at least one element, so end() is a string.
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
