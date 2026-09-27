<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class PrimaryConstructorDelegationSniff implements Sniff
{
    private const CONSTRUCTIBLE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT];

    private const SELF_TYPES = ['self', 'static'];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->getDeclarationName($stackPtr);

        if ($method === null) {
            return;
        }

        $ownerPtr = $this->constructibleOwner($tokens[$stackPtr]['conditions']);

        if ($ownerPtr === null) {
            return;
        }

        $properties = $phpcsFile->getMethodProperties($stackPtr);

        if ($properties['is_static'] === false) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($ownerPtr);

        if ($this->returnsDeclaringClass($phpcsFile, $properties['return_type'], $className) === false) {
            return;
        }

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->delegates($phpcsFile, $stackPtr, $className, $method) === true) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() returns an instance without routing through the primary constructor; build it'
                . ' with new self(...) / new static(...), or delegate to another static method of'
                . ' the class, so initialization stays in one place'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $stackPtr,
            'Missing',
            [$method]
        );
    }

    private function constructibleOwner(array $conditions): ?int
    {
        if ($conditions === []) {
            return null;
        }

        $ownerPtr = array_key_last($conditions);

        if (in_array($conditions[$ownerPtr], self::CONSTRUCTIBLE_SCOPES, true) === false) {
            return null;
        }

        return $ownerPtr;
    }

    private function returnsDeclaringClass(File $phpcsFile, string $returnType, ?string $className): bool
    {
        foreach (preg_split('/[|&]/', $returnType) ?: [$returnType] as $part) {
            $spelling = ltrim(trim($part), '?');
            $type = strtolower(ltrim($spelling, '\\'));

            if (in_array($type, self::SELF_TYPES, true) === true) {
                return true;
            }

            if (
                $className === null
                || $type !== strtolower($className)
            ) {
                continue;
            }

            if (
                str_starts_with($spelling, '\\') === false
                || $this->inGlobalNamespace($phpcsFile) === true
            ) {
                return true;
            }
        }

        return false;
    }

    private function inGlobalNamespace(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();
        $keywordPtr = $phpcsFile->findNext(T_NAMESPACE, 0);

        if ($keywordPtr === false) {
            return true;
        }

        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($keywordPtr + 1), null, true);

        return $namePtr === false || $tokens[$namePtr]['code'] !== T_STRING;
    }

    private function delegates(File $phpcsFile, int $stackPtr, ?string $className, string $method): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] === T_ANON_CLASS) {
                $pointer = $tokens[$pointer]['scope_closer'] ?? $closer;

                continue;
            }

            if (
                $tokens[$pointer]['code'] === T_NEW
                && $this->instantiatesDeclaringClass($phpcsFile, $pointer, $className) === true
            ) {
                return true;
            }

            if (
                $tokens[$pointer]['code'] === T_DOUBLE_COLON
                && $this->isSiblingStaticCall($phpcsFile, $pointer, $className, $method) === true
            ) {
                return true;
            }
        }

        return false;
    }

    private function instantiatesDeclaringClass(File $phpcsFile, int $newPtr, ?string $className): bool
    {
        $tokens = $phpcsFile->getTokens();
        $targetPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($newPtr + 1), null, true);

        if (
            $targetPtr !== false
            && $tokens[$targetPtr]['code'] === T_NS_SEPARATOR
        ) {
            $targetPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($targetPtr + 1), null, true);
        }

        if ($targetPtr === false) {
            return false;
        }

        return $this->namesDeclaringClass($phpcsFile, $targetPtr, $className);
    }

    private function isSiblingStaticCall(
        File $phpcsFile,
        int $colonPtr,
        ?string $className,
        string $method
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $ownerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($colonPtr - 1), null, true);

        if (
            $ownerPtr === false
            || $this->namesDeclaringClass($phpcsFile, $ownerPtr, $className) === false
        ) {
            return false;
        }

        $calleePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($colonPtr + 1), null, true);

        if (
            $calleePtr === false
            || $tokens[$calleePtr]['code'] !== T_STRING
        ) {
            return false;
        }

        if (strtolower($tokens[$calleePtr]['content']) === strtolower($method)) {
            return false;
        }

        $parenthesisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($calleePtr + 1), null, true);

        return $parenthesisPtr !== false && $tokens[$parenthesisPtr]['code'] === T_OPEN_PARENTHESIS;
    }

    private function namesDeclaringClass(File $phpcsFile, int $pointer, ?string $className): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$pointer]['code'];

        if (
            $code === T_SELF
            || $code === T_STATIC
        ) {
            return true;
        }

        if (
            $code !== T_STRING
            || $className === null
        ) {
            return false;
        }

        if (strtolower($tokens[$pointer]['content']) !== strtolower($className)) {
            return false;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), null, true);

        if (
            $afterPtr !== false
            && $tokens[$afterPtr]['code'] === T_NS_SEPARATOR
        ) {
            return false;
        }

        $beforePtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        if (
            $beforePtr === false
            || $tokens[$beforePtr]['code'] !== T_NS_SEPARATOR
        ) {
            return true;
        }

        $segmentPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($beforePtr - 1), null, true);

        if (
            $segmentPtr !== false
            && $tokens[$segmentPtr]['code'] === T_STRING
        ) {
            return false;
        }

        return $this->inGlobalNamespace($phpcsFile);
    }
}
