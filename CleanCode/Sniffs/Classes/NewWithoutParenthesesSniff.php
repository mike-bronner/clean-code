<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NewWithoutParenthesesSniff implements Sniff
{
    private const CLASS_REFERENCE_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_STATIC,
        T_SELF,
        T_PARENT,
        T_VARIABLE,
    ];

    private const DEREFERENCE_TOKENS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_PARENTHESIS,
    ];

    public function register(): array
    {
        return [T_NEW];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $referenceEnd = $this->classReferenceEnd($phpcsFile, $stackPtr);

        if ($referenceEnd === null) {
            return;
        }

        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, $referenceEnd + 1, null, true);

        if (
            $opener === false
            || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $closer = $tokens[$opener]['parenthesis_closer'] ?? null;

        if (
            $closer === null
            || $phpcsFile->findNext(T_WHITESPACE, $opener + 1, $closer, true) !== false
        ) {
            return;
        }

        $following = $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true);

        if (
            $following !== false
            && in_array($tokens[$following]['code'], self::DEREFERENCE_TOKENS, true)
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
                "Instantiate a class without empty parentheses; write \"new Foo\", not \"new Foo()\"",
                $stackPtr,
                'Found'
            );

        if ($fix === false) {
            return;
        }

        $this->removeParentheses($phpcsFile, $opener, $closer);
    }

    private function classReferenceEnd(File $phpcsFile, int $newPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $head = $phpcsFile->findNext(Tokens::$emptyTokens, $newPtr + 1, null, true);

        if ($head === false) {
            return null;
        }

        if ($tokens[$head]['code'] === T_OPEN_PARENTHESIS) {
            return $tokens[$head]['parenthesis_closer'] ?? null;
        }

        if (! in_array($tokens[$head]['code'], self::CLASS_REFERENCE_TOKENS, true)) {
            return null;
        }

        $end = $head;
        $segmentEnd = $this->segmentEnd($phpcsFile, $end);

        while ($segmentEnd !== null) {
            $end = $segmentEnd;
            $segmentEnd = $this->segmentEnd($phpcsFile, $end);
        }

        return $end;
    }

    private function segmentEnd(File $phpcsFile, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $operator = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);
        $member = $operator === false
            ? false
            : $phpcsFile->findNext(Tokens::$emptyTokens, $operator + 1, null, true);

        if ($member === false) {
            return null;
        }

        return match ([$tokens[$operator]['code'], $tokens[$member]['code']]) {
            [T_OBJECT_OPERATOR, T_STRING],
            [T_OBJECT_OPERATOR, T_VARIABLE],
            [T_DOUBLE_COLON, T_VARIABLE] => $member,
            [T_OBJECT_OPERATOR, T_OPEN_CURLY_BRACKET] => $tokens[$member]['bracket_closer'] ?? null,
            default => $tokens[$operator]['code'] === T_OPEN_SQUARE_BRACKET
                ? $tokens[$operator]['bracket_closer'] ?? null
                : null,
        };
    }

    private function removeParentheses(File $phpcsFile, int $opener, int $closer): void
    {
        $fixer = $phpcsFile->fixer;
        $start = $phpcsFile->findPrevious(T_WHITESPACE, $opener - 1, null, true) + 1;

        $fixer->beginChangeset();

        for ($pointer = $start; $pointer <= $closer; $pointer++) {
            $fixer->replaceToken($pointer, '');
        }

        $fixer->endChangeset();
    }
}
