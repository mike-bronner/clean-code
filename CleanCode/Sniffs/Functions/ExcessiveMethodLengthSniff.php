<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ExcessiveMethodLengthSniff implements Sniff
{
    public $minimum = 100;

    public bool $ignoreWhitespace = false;

    private const MODIFIER_TOKENS = [
        T_ABSTRACT,
        T_FINAL,
        T_PRIVATE,
        T_PROTECTED,
        T_PUBLIC,
        T_STATIC,
    ];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $minimum = $this->normalizedMinimum();
        $start = $this->declarationStart($phpcsFile, $stackPtr);

        $length = $this->ignoreWhitespace === true
            ? $this->executableLines($phpcsFile, $stackPtr)
            : $this->spannedLines($phpcsFile, $stackPtr, $start);

        if ($length < $minimum) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The %s %s() has %s lines of code, and the threshold is %s; a declaration '
                . 'this long is doing several jobs, so extract each one into its own '
                . 'method (see docs/phpmd/codesize-excessivemethodlength.md)',
            $start,
            'Found',
            [
                $this->describe($phpcsFile, $stackPtr),
                $name ?? 'anonymous',
                $length,
                $minimum,
            ]
        );
    }

    private function normalizedMinimum(): int
    {
        $minimum = is_numeric($this->minimum) === true ? (int) $this->minimum : 0;

        return $minimum < 1 ? 100 : $minimum;
    }

    private function declarationStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $found = $stackPtr;

        for ($i = $stackPtr - 1; $i >= 0; $i--) {
            $code = $tokens[$i]['code'];

            if (in_array($code, self::MODIFIER_TOKENS, true) === true) {
                $found = $i;

                continue;
            }

            if (
                $code === T_WHITESPACE
                || isset(Tokens::$commentTokens[$code]) === true
            ) {
                continue;
            }

            break;
        }

        return $found;
    }

    private function spannedLines(File $phpcsFile, int $stackPtr, int $start): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$stackPtr]['scope_closer'] ?? $this->signatureTerminator($phpcsFile, $stackPtr);

        return $tokens[$end]['line'] - $tokens[$start]['line'] + 1;
    }

    private function signatureTerminator(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $afterParameters = $tokens[$stackPtr]['parenthesis_closer'] ?? $stackPtr;

        $terminator = $phpcsFile->findNext(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET],
            $afterParameters + 1
        );

        if (
            $terminator === false
            || $tokens[$terminator]['code'] !== T_SEMICOLON
        ) {
            return $stackPtr;
        }

        return $terminator;
    }

    private function executableLines(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return 0;
        }

        $lines = [];

        for ($i = $opener; $i <= $closer; $i++) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_WHITESPACE
                || isset(Tokens::$commentTokens[$code]) === true
            ) {
                continue;
            }

            $lines[$tokens[$i]['line']] = true;
        }

        return count($lines);
    }

    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach ($conditions as $code) {
            if (in_array($code, Tokens::$ooScopeTokens, true) === true) {
                return 'method';
            }
        }

        return 'function';
    }
}
