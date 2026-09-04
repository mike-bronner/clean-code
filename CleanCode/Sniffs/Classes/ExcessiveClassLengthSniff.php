<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ExcessiveClassLengthSniff implements Sniff
{
    public int $minimum = 1000;

    public bool $ignoreWhitespace = false;

    private const DECLARATION_MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_READONLY,
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // A class the tokenizer never found a closing brace for — an
        // unterminated class body — carries no scope, so it has no end line to
        // measure from and nothing this rule can honestly report.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $declarationPtr = $this->declarationStart($phpcsFile, $stackPtr);

        $length = $this->ignoreWhitespace === true
            ? $this->executableLines($phpcsFile, $stackPtr)
            : $this->physicalLines($phpcsFile, $stackPtr, $declarationPtr);

        if ($length < $this->minimum) {
            return;
        }

        $phpcsFile->addError(
            'The class %s has %s lines of code. Current threshold is %s. Avoid really long classes.',
            $declarationPtr,
            'TooLong',
            [
                $phpcsFile->getDeclarationName($stackPtr),
                $length,
                $this->minimum,
            ]
        );
    }

    private function declarationStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $stackPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if (
                $previous === false
                || in_array($tokens[$previous]['code'], self::DECLARATION_MODIFIERS, true) === false
            ) {
                return $start;
            }

            $start = $previous;
        }
    }

    private function physicalLines(File $phpcsFile, int $stackPtr, int $declarationPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];

        return ($tokens[$closer]['line'] - $tokens[$declarationPtr]['line'] + 1);
    }

    private function executableLines(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $total = 0;

        for ($i = ($tokens[$stackPtr]['scope_opener'] + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_FUNCTION) {
                continue;
            }

            // Methods of a nested anonymous class, and functions declared inside
            // a method body, belong to that inner scope rather than to this
            // class. Their lines still reach the total through the enclosing
            // method, exactly as they do in PDepend.
            if (array_key_last($tokens[$i]['conditions']) !== $stackPtr) {
                continue;
            }

            // An abstract method has no scope to measure.
            if (isset($tokens[$i]['scope_opener'], $tokens[$i]['scope_closer']) === false) {
                continue;
            }

            $total += $this->bodyLines($phpcsFile, $tokens[$i]['scope_opener'], $tokens[$i]['scope_closer']);
        }

        return $total;
    }

    private function bodyLines(File $phpcsFile, int $opener, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $lines = [];

        for ($i = $opener; $i <= $closer; $i++) {
            if (
                $tokens[$i]['code'] === T_WHITESPACE
                || isset(Tokens::$commentTokens[$tokens[$i]['code']]) === true
            ) {
                continue;
            }

            $lines[$tokens[$i]['line']] = true;
        }

        return count($lines);
    }
}
