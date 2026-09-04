<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class MethodNestingLevelSniff implements Sniff
{
    private const MAX_NESTING_LEVEL = 2;

    private const NESTING_TOKENS = [
        T_IF => true,
        T_ELSEIF => true,
        T_ELSE => true,
        T_FOR => true,
        T_FOREACH => true,
        T_WHILE => true,
        T_DO => true,
        T_SWITCH => true,
        T_MATCH => true,
        T_TRY => true,
        T_CATCH => true,
        T_FINALLY => true,
        T_CLOSURE => true,
    ];

    public function register(): array
    {
        return [
            T_IF,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_SWITCH,
            T_MATCH,
            T_TRY,
            T_CLOSURE,
            T_FN,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$stackPtr]['conditions'];

        // Braceless/abstract/interface bodies and the trailing while of a
        // do-while have no scope to measure.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        // The standard governs method bodies; skip top-level script code and
        // closures not enclosed by a function.
        if (in_array(T_FUNCTION, $conditions, true) === false) {
            return;
        }

        // Two-word `else if` tokenizes as a bare `T_ELSE` followed by a fresh
        // `T_IF` that sits at the same nesting level as the chain's leading
        // `if` (its conditions don't include the preceding branch). Reporting
        // that `T_IF` would emit a duplicate error for a level the leading `if`
        // already reports, so treat it as a continuation and skip it — mirroring
        // the one-word `elseif`, which is a single `T_ELSEIF` token that is not
        // registered. The `else`/`elseif` continuation still counts as a level
        // for statements nested inside the branch via NESTING_TOKENS.
        if ($tokens[$stackPtr]['code'] === T_IF) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

            if (
                $previous !== false
                && $tokens[$previous]['code'] === T_ELSE
            ) {
                return;
            }
        }

        $level = 1 + $this->arrowFunctionDepth($phpcsFile, $stackPtr, $conditions);

        foreach ($conditions as $conditionCode) {
            if (isset(self::NESTING_TOKENS[$conditionCode]) === true) {
                $level++;
            }
        }

        if ($level <= self::MAX_NESTING_LEVEL) {
            return;
        }

        $phpcsFile->addError(
            'Method nesting level (%s) exceeds the maximum of %s; refactor to reduce nesting',
            $stackPtr,
            'MaxExceeded',
            [$level, self::MAX_NESTING_LEVEL]
        );
    }

    private function arrowFunctionDepth(File $phpcsFile, int $stackPtr, array $conditions): int
    {
        $tokens = $phpcsFile->getTokens();
        $bodyStart = $this->declarationBodyStart($tokens, $conditions);
        $depth = 0;
        $pointer = $stackPtr;

        while (($pointer = $phpcsFile->findPrevious(T_FN, $pointer - 1, $bodyStart)) !== false) {
            if (
                isset($tokens[$pointer]['scope_closer']) === true
                && $tokens[$pointer]['scope_closer'] >= $stackPtr
            ) {
                $depth++;
            }
        }

        return $depth;
    }

    private function declarationBodyStart(array $tokens, array $conditions): int
    {
        foreach ($conditions as $pointer => $conditionCode) {
            if (
                $conditionCode === T_FUNCTION
                && isset($tokens[$pointer]['scope_opener']) === true
            ) {
                return $tokens[$pointer]['scope_opener'];
            }
        }

        return 0;
    }
}
