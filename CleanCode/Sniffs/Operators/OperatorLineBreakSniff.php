<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class OperatorLineBreakSniff implements Sniff
{
    public function register(): array
    {
        return array_merge(
            array_values(Tokens::$assignmentTokens),
            array_values(Tokens::$comparisonTokens),
            array_values(Tokens::$booleanOperators),
            [T_STRING_CONCAT]
        );
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ((new ConditionOperatorOwnership())->isDeferredToOneConditionPerLine($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // Skip comments too when locating the next code token: a comment
        // explaining why an expression wraps (`$a . // note` then the operand
        // on the next line) must not mask the dangling operator.
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($next === false) {
            return;
        }

        if ($tokens[$next]['line'] <= $tokens[$stackPtr]['line']) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            "A \"%s\" operator must not end a line; place it at the start of the continuation line instead",
            $stackPtr,
            'OperatorAtLineEnd',
            [$tokens[$stackPtr]['content']]
        );

        if ($fix === false) {
            return;
        }

        $this->moveToContinuationLine($phpcsFile, $stackPtr, $next);
    }

    // Position only: the token sequence is unchanged, and just the line break
    // moves from after the operator to before it. The operator is inserted
    // before the next *code* token, which puts it after that line's indent
    // rather than in front of it.
    private function moveToContinuationLine(File $phpcsFile, int $stackPtr, int $next): void
    {
        $tokens = $phpcsFile->getTokens();

        $phpcsFile->fixer
            ->beginChangeset();
        $phpcsFile->fixer
            ->replaceToken($stackPtr, '');

        // The space that sat in front of the operator would otherwise be left
        // hanging off the end of the line.
        if ($tokens[$stackPtr - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer
                ->replaceToken($stackPtr - 1, '');
        }

        $phpcsFile->fixer
            ->addContentBefore($next, "{$tokens[$stackPtr]['content']} ");
        $phpcsFile->fixer
            ->endChangeset();
    }
}
