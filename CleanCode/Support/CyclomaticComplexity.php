<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;

final class CyclomaticComplexity
{
    private const DECISION_TOKENS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_CASE,
        T_CATCH,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_INLINE_THEN,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_WHILE,
    ];

    public function forDeclaration(File $phpcsFile, int $declarationPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$declarationPtr]['scope_opener'] ?? null;
        $closer = $tokens[$declarationPtr]['scope_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return 1;
        }

        $complexity = 1;
        $ptr = $opener;

        while (++$ptr < $closer) {
            $code = $tokens[$ptr]['code'];

            if ($this->opensSkippedBody($tokens, $ptr) === true) {
                $ptr = $tokens[$ptr]['scope_closer'] ?? $ptr;

                continue;
            }

            if (in_array($code, self::DECISION_TOKENS, true) === true) {
                $complexity++;
            }
        }

        return $complexity;
    }

    private function opensSkippedBody(array $tokens, int $ptr): bool
    {
        if ($tokens[$ptr]['code'] === T_FUNCTION) {
            return true;
        }

        if ($tokens[$ptr]['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        $owner = $tokens[$ptr]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_ANON_CLASS;
    }
}
