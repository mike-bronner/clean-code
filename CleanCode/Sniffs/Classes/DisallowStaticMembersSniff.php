<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

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

    public function register(): array
    {
        return [T_STATIC];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // A member modifier lives directly in a class-like body; anything whose
        // innermost scope is a function (local `static`, `new static`,
        // `static::`, static closures) is not a member declaration.
        $conditions = $tokens[$stackPtr]['conditions'];

        if ($conditions === []) {
            return;
        }

        if (in_array(end($conditions), Tokens::$ooScopeTokens, true) === false) {
            return;
        }

        // Walk past whitespace, comments, and sibling modifiers to the token
        // the `static` actually modifies.
        $skip = array_merge(array_values(Tokens::$emptyTokens), self::MEMBER_MODIFIERS);
        $declaratorPtr = $phpcsFile->findNext($skip, ($stackPtr + 1), null, true);

        if ($declaratorPtr === false) {
            return;
        }

        if ($tokens[$declaratorPtr]['code'] === T_FUNCTION) {
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

        $phpcsFile->addError(
            'Static property %s is not allowed; a class should be instantiated, not accessed statically',
            $staticPtr,
            'StaticProperty',
            [$tokens[$propertyPtr]['content']]
        );
    }
}
