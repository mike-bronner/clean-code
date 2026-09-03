<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ThrowOnlyMethodOverrideSniff implements Sniff
{
    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->getDeclarationName($stackPtr);

        // Defensive only, and no fixture can pin it: PHPCS gives a closure its
        // own T_CLOSURE token, which this sniff never registers for, so the
        // one nameless T_FUNCTION is a truncated declaration — and a
        // truncation deep enough to strip the name also strips the body the
        // checks below need.
        if ($method === null) {
            return;
        }

        // An abstract declaration or an interface signature has no body.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->declaresHierarchy($phpcsFile, $tokens[$stackPtr]['conditions']) === false) {
            return;
        }

        if ($this->bodyIsOneThrow($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() stubs out an inherited method with a single throw; a subtype that refuses'
                . ' behaviour its supertype promises breaks Liskov Substitution. Split the'
                . ' hierarchy, or segregate the interface so this method is never promised,'
                . ' instead of throwing (see docs/standards/pattern-solid.md)',
            $stackPtr,
            'RefusedBequest',
            [$method]
        );
    }

    private function declaresHierarchy(File $phpcsFile, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }

        $ownerPtr = array_key_last($conditions);

        return $phpcsFile->findExtendedClassName($ownerPtr) !== false
            || $phpcsFile->findImplementedInterfaceNames($ownerPtr) !== false;
    }

    private function bodyIsOneThrow(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closerPtr = $tokens[$stackPtr]['scope_closer'];
        $firstPtr = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($tokens[$stackPtr]['scope_opener'] + 1),
            $closerPtr,
            true
        );

        if (
            $firstPtr === false
            || $tokens[$firstPtr]['code'] !== T_THROW
        ) {
            return false;
        }

        $endPtr = $phpcsFile->findEndOfStatement($firstPtr);

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true) === $closerPtr;
    }
}
