<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;

final class ParameterDeclaration
{
    public static function isPlainParameter(File $phpcsFile, int $variablePtr): bool
    {
        $ownerPtr = self::owningFunction($phpcsFile, $variablePtr);

        if ($ownerPtr === null) {
            return false;
        }

        $parameter = self::parameterAt($phpcsFile, $ownerPtr, $variablePtr);

        // A variable inside a function's own parameter list that
        // getMethodParameters() does not report back is treated as plain: it
        // declares no property either way, which is the answer both exempting
        // callers need. Defensive only — a parameter default must be a constant
        // expression, so a parameter's own name token is the only variable that
        // can stand in a parameter list.
        return $parameter === null || isset($parameter['property_visibility']) === false;
    }

    public static function isPromotedParameter(File $phpcsFile, int $variablePtr): bool
    {
        $ownerPtr = self::owningFunction($phpcsFile, $variablePtr);

        if ($ownerPtr === null) {
            return false;
        }

        $parameter = self::parameterAt($phpcsFile, $ownerPtr, $variablePtr);

        return $parameter !== null && isset($parameter['property_visibility']) === true;
    }

    private static function owningFunction(File $phpcsFile, int $variablePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$variablePtr]['nested_parenthesis']) === true) {
            return null;
        }

        $openers = array_keys($tokens[$variablePtr]['nested_parenthesis']);
        $ownerPtr = $tokens[array_pop($openers)]['parenthesis_owner'] ?? null;

        if (
            $ownerPtr === null
            || $tokens[$ownerPtr]['code'] !== T_FUNCTION
        ) {
            return null;
        }

        return $ownerPtr;
    }

    private static function parameterAt(File $phpcsFile, int $ownerPtr, int $variablePtr): ?array
    {
        foreach ($phpcsFile->getMethodParameters($ownerPtr) as $parameter) {
            if ($parameter['token'] === $variablePtr) {
                return $parameter;
            }
        }

        return null;
    }
}
