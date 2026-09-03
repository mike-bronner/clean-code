<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ControlStructures;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowCountInLoopExpressionSniff implements Sniff
{
    private const SIZE_FUNCTIONS = [
        'count',
        'sizeof',
    ];

    private const LOOP_TOKENS = [
        T_FOR,
        T_WHILE,
    ];

    private const NESTING_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
    ];

    private const NESTING_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    public function register(): array
    {
        return self::LOOP_TOKENS;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $condition = $this->conditionRange($phpcsFile, $stackPtr);

        if ($condition === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        [$start, $end] = $condition;
        $claimed = $this->nestedConditions($phpcsFile, $start, $end);

        for ($i = $start; $i < $end; $i++) {
            // A nested loop's condition is that loop's own to report: it is
            // registered too, and its pass covers exactly this range. Step over
            // it, and only it — the nested header's initialiser and increment,
            // and the nested body, are all still read here, because a call in
            // any of them runs on every evaluation of this condition.
            if (isset($claimed[$i]) === true) {
                $i = $claimed[$i];

                continue;
            }

            if ($this->isSizeFunctionCall($phpcsFile, $i) === false) {
                continue;
            }

            $phpcsFile->addError(
                '%s() must not be called in a loop condition; assign its result to a variable before the loop',
                $i,
                'Found',
                [$tokens[$i]['content']]
            );
        }
    }

    private function conditionRange(File $phpcsFile, int $loopPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$loopPtr]['parenthesis_opener'], $tokens[$loopPtr]['parenthesis_closer']) === false) {
            return null;
        }

        $start = ($tokens[$loopPtr]['parenthesis_opener'] + 1);
        $closer = $tokens[$loopPtr]['parenthesis_closer'];

        if ($tokens[$loopPtr]['code'] !== T_FOR) {
            return [$start, $closer];
        }

        $separators = $this->headerSeparators($phpcsFile, $start, $closer);

        if (isset($separators[0]) === false) {
            return null;
        }

        return [($separators[0] + 1), ($separators[1] ?? $closer)];
    }

    private function headerSeparators(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $separators = [];
        $depth = 0;

        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];

            if (in_array($code, self::NESTING_OPENERS, true) === true) {
                $depth++;
            } elseif (in_array($code, self::NESTING_CLOSERS, true) === true) {
                $depth--;
            } elseif (
                $code === T_SEMICOLON
                && $depth === 0
            ) {
                $separators[] = $i;
            }
        }

        return $separators;
    }

    private function nestedConditions(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $ranges = [];

        for ($i = $start; $i < $end; $i++) {
            if (in_array($tokens[$i]['code'], self::LOOP_TOKENS, true) === false) {
                continue;
            }

            $nested = $this->conditionRange($phpcsFile, $i);

            if ($nested === null) {
                continue;
            }

            $ranges[$nested[0]] = $nested[1];
        }

        return $ranges;
    }

    private function isSizeFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array(strtolower($tokens[$stackPtr]['content']), self::SIZE_FUNCTIONS, true) === false) {
            return false;
        }

        if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->isFirstClassCallable($phpcsFile, $stackPtr) === false;
    }

    private function isFirstClassCallable(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openerPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($openerPtr === false) {
            return false;
        }

        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), null, true);

        if (
            $ellipsis === false
            || $tokens[$ellipsis]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        $afterEllipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsis + 1), null, true);

        return $afterEllipsis !== false && $tokens[$afterEllipsis]['code'] === T_CLOSE_PARENTHESIS;
    }
}
