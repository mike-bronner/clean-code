<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

final class ConditionOperatorOwnership
{
    private const BRACKET_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];

    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR];

    public static function isDeferredToOneConditionPerLine(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $innermostOpener = max(array_keys($tokens[$stackPtr]['nested_parenthesis']));

        if (isset($tokens[$innermostOpener]['parenthesis_owner']) === false) {
            return false;
        }

        $owner = $tokens[$innermostOpener]['parenthesis_owner'];

        if (in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true) === false) {
            return false;
        }

        $region = self::checkedRegion($phpcsFile, $owner);

        if ($region === null) {
            return false;
        }

        [$regionStart, $regionEnd] = $region;

        if (
            $stackPtr <= $regionStart
            || $stackPtr >= $regionEnd
        ) {
            return false;
        }

        if (isset(Tokens::$booleanOperators[$tokens[$stackPtr]['code']]) === true) {
            return true;
        }

        // No top-level boolean in the checked region: OneConditionPerLine reads
        // it as a single condition and collapses any wrap in it wholesale.
        return self::findTopLevelTokens(
            $phpcsFile,
            ($regionStart + 1),
            ($regionEnd - 1),
            array_keys(Tokens::$booleanOperators)
        ) === [];
    }

    public static function checkedRegion(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]['parenthesis_opener']) === false
            || isset($tokens[$stackPtr]['parenthesis_closer']) === false
        ) {
            return null;
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        if ($tokens[$stackPtr]['code'] !== T_FOR) {
            return [$opener, $closer];
        }

        $semicolons = self::findTopLevelTokens($phpcsFile, ($opener + 1), ($closer - 1), [T_SEMICOLON]);

        return count($semicolons) === 2 ? [$semicolons[0], $semicolons[1]] : null;
    }

    public static function findTopLevelTokens(File $phpcsFile, int $start, int $end, array $codes): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];

                continue;
            }

            if (
                in_array($tokens[$i]['code'], self::BRACKET_OPENERS, true) === true
                && isset($tokens[$i]['bracket_closer']) === true
            ) {
                $i = $tokens[$i]['bracket_closer'];

                continue;
            }

            if (in_array($tokens[$i]['code'], $codes, true) === true) {
                $pointers[] = $i;
            }
        }

        return $pointers;
    }
}
