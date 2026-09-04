<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowNewlineAroundEvaluativeOperatorsSniff implements Sniff
{
    public function register(): array
    {
        return [
            T_GREATER_THAN,
            T_INSTANCEOF,
            T_IS_EQUAL,
            T_IS_GREATER_OR_EQUAL,
            T_IS_IDENTICAL,
            T_IS_NOT_EQUAL,
            T_IS_NOT_IDENTICAL,
            T_IS_SMALLER_OR_EQUAL,
            T_LESS_THAN,
            T_SPACESHIP,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $previous !== false
            && $tokens[$previous]['line'] !== $tokens[$stackPtr]['line']
        ) {
            $this->handleNewline($phpcsFile, $stackPtr, $previous, 'before');
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $next !== false
            && $tokens[$next]['line'] !== $tokens[$stackPtr]['line']
        ) {
            $this->handleNewline($phpcsFile, $stackPtr, $next, 'after');
        }
    }

    private function handleNewline(File $phpcsFile, int $operatorPtr, int $operandPtr, string $side): void
    {
        $tokens = $phpcsFile->getTokens();
        $error = "Newline found {$side} evaluative operator \"%s\"";
        $code = ($side === 'before') ? 'FoundBefore' : 'FoundAfter';
        $data = [$tokens[$operatorPtr]['content']];
        [$start, $end] = ($side === 'before') ? [$operandPtr, $operatorPtr] : [$operatorPtr, $operandPtr];

        if ($phpcsFile->findNext(Tokens::$commentTokens, ($start + 1), $end) !== false) {
            $phpcsFile->addError($error, $operatorPtr, $code, $data);

            return;
        }

        if ($phpcsFile->addFixableError($error, $operatorPtr, $code, $data) === false) {
            return;
        }

        $phpcsFile->fixer
            ->beginChangeset();

        for ($i = ($start + 1); $i < $end; $i++) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        $phpcsFile->fixer
            ->addContent($start, ' ');
        $phpcsFile->fixer
            ->endChangeset();
    }
}
