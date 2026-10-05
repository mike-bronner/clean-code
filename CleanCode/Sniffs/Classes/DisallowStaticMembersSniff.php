<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowStaticMembersSniff implements Sniff
{
    private const MEMBER_MODIFIERS = [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_FINAL,
        T_ABSTRACT,
        T_READONLY,
        T_VAR,
    ];

    public function __construct(
        private InheritedMembers $inheritedMembers = new InheritedMembers
    ) {
    }

    public function register(): array
    {
        return [T_STATIC];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $conditions = $tokens[$stackPtr]['conditions'];

        if ($conditions === []) {
            return;
        }

        if (in_array(end($conditions), Tokens::$ooScopeTokens, true) === false) {
            return;
        }

        $skip = array_merge(array_values(Tokens::$emptyTokens), self::MEMBER_MODIFIERS);
        $declaratorPtr = $phpcsFile->findNext($skip, ($stackPtr + 1), null, true);

        if ($declaratorPtr === false) {
            return;
        }

        if ($tokens[$declaratorPtr]['code'] === T_FUNCTION) {
            if ($this->inheritedMembers->overridesStaticMethod($phpcsFile, $declaratorPtr) === true) {
                return;
            }

            $this->reportStaticMethod($phpcsFile, $stackPtr, $declaratorPtr);

            return;
        }

        $this->reportStaticProperty($phpcsFile, $stackPtr, $declaratorPtr);
    }

    private function reportStaticMethod(File $phpcsFile, int $staticPtr, int $functionPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $openParen = $phpcsFile->findNext([T_OPEN_PARENTHESIS], ($functionPtr + 1), null);
        $namePtr = $phpcsFile->findNext(
                [T_STRING],
                ($functionPtr + 1),
                ($openParen === false ? null : $openParen)
            );
        $name = ($namePtr === false ? 'method' : $tokens[$namePtr]['content']) . '()';

        $phpcsFile->addError(
                'Static method %s is not allowed; a class should be instantiated, not called statically',
                $staticPtr,
                'StaticMethod',
                [$name]
            );
    }

    private function reportStaticProperty(File $phpcsFile, int $staticPtr, int $declaratorPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $boundary = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $declaratorPtr, null);
        $propertyPtr = $phpcsFile->findNext(
                [T_VARIABLE],
                $declaratorPtr,
                ($boundary === false ? null : $boundary)
            );

        if ($propertyPtr === false) {
            return;
        }

        $property = $tokens[$propertyPtr]['content'];

        if (
            $this->inheritedMembers->redeclaresStaticProperty($phpcsFile, $declaratorPtr, $property) === true
            || $this->inheritedMembers->fulfilsStaticRead($phpcsFile, $declaratorPtr, $property) === true
        ) {
            return;
        }

        $phpcsFile->addError(
                'Static property %s is not allowed; a class should be instantiated, not accessed statically',
                $staticPtr,
                'StaticProperty',
                [$property]
            );
    }
}
