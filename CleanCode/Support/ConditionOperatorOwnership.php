<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

final class ConditionOperatorOwnership
{
    private const BRACKET_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];

    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR];

    public const LOGICAL_KEYWORDS = [T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR];

    private const EXPRESSION_BOUNDARIES = [
        T_SEMICOLON,
        T_COMMA,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_TAG,
        T_FN,
    ];

    public function expressionOwners(): array
    {
        return array_merge($this->assignmentCodes(), [T_RETURN, T_FN_ARROW, T_MATCH_ARROW]);
    }

    public function expressionRegion(File $phpcsFile, int $stackPtr): ?array
    {
        if ($this->isInsideCondition($phpcsFile, $stackPtr) === true) {
            return null;
        }

        $start = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($start === false) {
            return null;
        }

        $end = $phpcsFile->findPrevious(
                Tokens::$emptyTokens,
                ($this->expressionBoundary($phpcsFile, $stackPtr, $start) - 1),
                $stackPtr,
                true
            );

        return $end >= $start ? [$start, $end] : null;
    }

    public function isDeferredToOneConditionPerLine(File $phpcsFile, int $stackPtr): bool
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

        $region = $this->checkedRegion($phpcsFile, $owner);

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

        return $this->findTopLevelTokens(
                $phpcsFile,
                ($regionStart + 1),
                ($regionEnd - 1),
                array_keys(Tokens::$booleanOperators)
            ) === [];
    }

    private function isInsideCondition(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $opener) {
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if (
                $owner === null
                || in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true) === false
            ) {
                continue;
            }

            $region = $this->checkedRegion($phpcsFile, $owner);

            if (
                $region !== null
                && $stackPtr > $region[0]
                && $stackPtr < $region[1]
            ) {
                return true;
            }
        }

        return false;
    }

    private function expressionBoundary(File $phpcsFile, int $stackPtr, int $start): int
    {
        $boundaries = array_merge(self::EXPRESSION_BOUNDARIES, $this->assignmentCodes());

        if (in_array($phpcsFile->getTokens()[$stackPtr]['code'], $this->assignmentCodes(), true) === true) {
            $boundaries = array_merge($boundaries, self::LOGICAL_KEYWORDS);
        }

        return $this->nextTopLevelToken($phpcsFile, $start, ($phpcsFile->numTokens - 1), $boundaries)
            ?? $phpcsFile->numTokens;
    }

    private function assignmentCodes(): array
    {
        return array_values(array_diff_key(Tokens::$assignmentTokens, [T_DOUBLE_ARROW => true]));
    }

    public function checkedRegion(File $phpcsFile, int $stackPtr): ?array
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

        $semicolons = $this->findTopLevelTokens($phpcsFile, ($opener + 1), ($closer - 1), [T_SEMICOLON]);

        return count($semicolons) === 2 ? [$semicolons[0], $semicolons[1]] : null;
    }

    public function findTopLevelTokens(File $phpcsFile, int $start, int $end, array $codes): array
    {
        $pointers = [];

        for (
            $i = $this->nextTopLevelToken($phpcsFile, $start, $end, $codes);
            $i !== null;
            $i = $this->nextTopLevelToken($phpcsFile, ($i + 1), $end, $codes)
        ) {
            $pointers[] = $i;
        }

        return $pointers;
    }

    private function nextTopLevelToken(File $phpcsFile, int $start, int $end, array $codes): ?int
    {
        $tokens = $phpcsFile->getTokens();

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
                return $i;
            }
        }

        return null;
    }
}
