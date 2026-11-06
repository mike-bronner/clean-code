<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class OneConditionPerLineSniff implements Sniff
{
    private const CONTROL_STRUCTURES = [T_IF, T_ELSEIF, T_WHILE, T_FOR];

    public function register(): array
    {
        return array_merge(self::CONTROL_STRUCTURES, (new ConditionOperatorOwnership)->expressionOwners());
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if (in_array($phpcsFile->getTokens()[$stackPtr]['code'], self::CONTROL_STRUCTURES, true) === false) {
            $this->processExpression($phpcsFile, $stackPtr);

            return;
        }

        $region = (new ConditionOperatorOwnership)->checkedRegion($phpcsFile, $stackPtr);

        if ($region === null) {
            return;
        }

        [$boundaryStart, $boundaryEnd] = $region;

        $regionStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($boundaryStart + 1), $boundaryEnd, true);

        if ($regionStart === false) {
            return;
        }

        $regionEnd = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($boundaryEnd - 1), $boundaryStart, true);

        $operators = (new ConditionOperatorOwnership)->findTopLevelTokens(
                $phpcsFile,
                $regionStart,
                $regionEnd,
                array_keys(Tokens::$booleanOperators)
            );

        if ($operators === []) {
            $this->processSingleCondition(
                    $phpcsFile,
                    $stackPtr,
                    $boundaryStart,
                    $boundaryEnd,
                    $regionStart,
                    $regionEnd
                );

            return;
        }

        $this->processMultiCondition($phpcsFile, $stackPtr, $regionStart, $regionEnd, $operators);
    }

    private function processExpression(File $phpcsFile, int $stackPtr): void
    {
        $region = (new ConditionOperatorOwnership)->expressionRegion($phpcsFile, $stackPtr);

        if ($region === null) {
            return;
        }

        [$regionStart, $regionEnd] = $region;
        $keywords = (new ConditionOperatorOwnership)->findTopLevelTokens(
                $phpcsFile,
                $regionStart,
                $regionEnd,
                ConditionOperatorOwnership::LOGICAL_KEYWORDS
            );
        $conditions = $keywords;
        $conditionEnd = $regionEnd;
        $ternaries = [];

        foreach ($this->segments($phpcsFile, $regionStart, $regionEnd, $keywords) as [$pieceStart, $pieceEnd]) {
            $ternary = $this->ternaryOperators($phpcsFile, $pieceStart, $pieceEnd);
            $segments = $this->segments($phpcsFile, $pieceStart, $pieceEnd, $ternary);
            $condition = array_shift($segments);
            $conditions = array_merge($conditions, $this->booleanOperators($phpcsFile, ...$condition));
            $conditionEnd = $keywords === [] ? $condition[1] : $regionEnd;
            $ternaries[] = [$ternary, $condition, $segments];
        }

        $leading = $this->operatorsNotEndingTheirLine($phpcsFile, $conditions);

        if ($leading !== []) {
            $this->processMultiCondition($phpcsFile, $stackPtr, $regionStart, $conditionEnd, $leading);
        }

        foreach ($ternaries as [$ternary, $condition, $segments]) {
            $this->processTernary($phpcsFile, $stackPtr, $ternary, $condition, $segments);
        }
    }

    private function processTernary(
        File $phpcsFile,
        int $stackPtr,
        array $ternary,
        array $condition,
        array $segments
    ): void {
        $tokens = $phpcsFile->getTokens();
        [$conditionStart, $conditionEnd] = $condition;
        $branches = array_map(
                fn (array $segment): array => $this->booleanOperators($phpcsFile, ...$segment),
                $segments
            );
        $hasBranchConditions = array_filter($branches) !== [];
        $hasSplitCondition = $this->booleanOperators($phpcsFile, $conditionStart, $conditionEnd) !== []
            && $tokens[$conditionStart]['line'] !== $tokens[$conditionEnd]['line'];

        if (
            $hasSplitCondition === false
            && $hasBranchConditions === false
        ) {
            return;
        }

        $indent = "{$this->lineIndent($phpcsFile, $stackPtr)}    ";
        $leadingTernary = array_values(array_filter(
                $ternary,
                fn (int $operator): bool => $this->isElvisColon($phpcsFile, $operator) === false
            ));
        $this->reportTrailingOperators(
                $phpcsFile,
                $leadingTernary,
                $indent,
                "Ternary operator \"%s\" must start its own line in a multi-condition expression",
                'TernaryOperatorNotLeading'
            );

        foreach ($branches as $branch) {
            $this->reportTrailingOperators(
                    $phpcsFile,
                    $this->operatorsNotEndingTheirLine($phpcsFile, $branch),
                    "{$indent}    ",
                    "Boolean operator \"%s\" must lead its condition line, not trail the previous one",
                    'BooleanOperatorNotLeading'
                );
        }
    }

    private function booleanOperators(File $phpcsFile, int $start, int $end): array
    {
        if ($end < $start) {
            return [];
        }

        return (new ConditionOperatorOwnership)->findTopLevelTokens(
                $phpcsFile,
                $start,
                $end,
                array_keys(Tokens::$booleanOperators)
            );
    }

    private function ternaryOperators(File $phpcsFile, int $regionStart, int $regionEnd): array
    {
        $tokens = $phpcsFile->getTokens();
        $operators = (new ConditionOperatorOwnership)->findTopLevelTokens(
                $phpcsFile,
                $regionStart,
                $regionEnd,
                [T_INLINE_THEN, T_INLINE_ELSE]
            );

        if (
            $operators === []
            || $tokens[$operators[0]]['code'] !== T_INLINE_THEN
        ) {
            return [];
        }

        return $operators;
    }

    private function segments(File $phpcsFile, int $regionStart, int $regionEnd, array $dividers): array
    {
        $starts = [$regionStart];
        $ends = [];

        foreach ($dividers as $operator) {
            $ends[] = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operator - 1), null, true);
            $starts[] = $phpcsFile->findNext(Tokens::$emptyTokens, ($operator + 1), null, true);
        }

        $ends[] = $regionEnd;

        return array_map(null, $starts, $ends);
    }

    private function isElvisColon(File $phpcsFile, int $operator): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(T_WHITESPACE, ($operator - 1), null, true);

        return $tokens[$operator]['code'] === T_INLINE_ELSE
            && $tokens[$previous]['code'] === T_INLINE_THEN;
    }

    private function reportTrailingOperators(
        File $phpcsFile,
        array $operators,
        string $indent,
        string $message,
        string $code
    ): void {
        $tokens = $phpcsFile->getTokens();

        foreach ($operators as $operator) {
            $previous = $phpcsFile->findPrevious(T_WHITESPACE, ($operator - 1), null, true);

            if ($tokens[$previous]['line'] !== $tokens[$operator]['line']) {
                continue;
            }

            $fix = $phpcsFile->addFixableError($message, $operator, $code, [$tokens[$operator]['content']]);

            if ($fix === false) {
                continue;
            }

            $phpcsFile->fixer
                ->beginChangeset();
            $this->moveOperatorToOwnLine($phpcsFile, $operator, $indent);
            $phpcsFile->fixer
                ->endChangeset();
        }
    }

    private function operatorsNotEndingTheirLine(File $phpcsFile, array $operators): array
    {
        $tokens = $phpcsFile->getTokens();

        return array_values(array_filter(
                $operators,
                static function (int $operator) use ($phpcsFile, $tokens): bool {
                    $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($operator + 1), null, true);

                    return $tokens[$next]['line'] === $tokens[$operator]['line'];
                }
            ));
    }

    private function processSingleCondition(
        File $phpcsFile,
        int $stackPtr,
        int $boundaryStart,
        int $boundaryEnd,
        int $regionStart,
        int $regionEnd
    ): void {
        $tokens = $phpcsFile->getTokens();

        $isSplit = $tokens[$regionStart]['line'] !== $tokens[$regionEnd]['line'];
        $offKeywordLine = $tokens[$stackPtr]['code'] !== T_FOR
            && $tokens[$regionStart]['line'] !== $tokens[$stackPtr]['line'];

        if (
            $isSplit === false
            && $offKeywordLine === false
        ) {
            return;
        }

        $error = "A single condition must stay on one line with its \"%s\" keyword";
        $code = 'SingleConditionNotOnOneLine';
        $data = [strtolower($tokens[$stackPtr]['content'])];

        $hasComment = $phpcsFile->findNext(Tokens::$commentTokens, ($boundaryStart + 1), $boundaryEnd) !== false;

        if ($hasComment === true) {
            $phpcsFile->addError($error, $stackPtr, $code, $data);

            return;
        }

        $fix = $phpcsFile->addFixableError($error, $stackPtr, $code, $data);

        if ($fix === false) {
            return;
        }

        $joined = $this->joinedCondition($phpcsFile, $regionStart, $regionEnd);

        if ($tokens[$stackPtr]['code'] === T_FOR) {
            $joined = " {$joined}";
        }

        $phpcsFile->fixer
            ->beginChangeset();

        for ($i = ($boundaryStart + 1); $i < $boundaryEnd; $i++) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        $phpcsFile->fixer
            ->addContent($boundaryStart, $joined);
        $phpcsFile->fixer
            ->endChangeset();
    }

    private function processMultiCondition(
        File $phpcsFile,
        int $stackPtr,
        int $regionStart,
        int $regionEnd,
        array $operators
    ): void {
        $tokens = $phpcsFile->getTokens();
        $indent = "{$this->lineIndent($phpcsFile, $stackPtr)}    ";

        if ($tokens[$regionStart]['line'] === $tokens[$regionEnd]['line']) {
            $fix = $phpcsFile->addFixableError(
                    "Each condition of a multi-condition \"%s\" must be on its own line",
                    $stackPtr,
                    'MultipleConditionsOnOneLine',
                    [$this->contextLabel($tokens[$stackPtr])]
                );

            if ($fix === true) {
                $phpcsFile->fixer
                    ->beginChangeset();

                foreach ($operators as $operator) {
                    $this->moveOperatorToOwnLine($phpcsFile, $operator, $indent);
                }

                $phpcsFile->fixer
                    ->endChangeset();
            }

            return;
        }

        $this->reportTrailingOperators(
                $phpcsFile,
                $operators,
                $indent,
                "Boolean operator \"%s\" must lead its condition line, not trail the previous one",
                'BooleanOperatorNotLeading'
            );
    }

    private function contextLabel(array $token): string
    {
        return match ($token['code']) {
            T_FN_ARROW => 'fn',
            T_MATCH_ARROW => 'match',
            default => strtolower($token['content']),
        };
    }

    private function joinedCondition(File $phpcsFile, int $start, int $end): string
    {
        $tokens = $phpcsFile->getTokens();
        $joined = '';
        $pendingSpace = false;

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] === T_WHITESPACE) {
                $pendingSpace = true;

                continue;
            }

            if (
                $pendingSpace === true
                && substr($joined, -1) !== '('
                && in_array($tokens[$i]['code'], [T_CLOSE_PARENTHESIS, T_COMMA, T_SEMICOLON], true) === false
            ) {
                $joined .= ' ';
            }

            $joined .= $tokens[$i]['content'];
            $pendingSpace = false;
        }

        return $joined;
    }

    private function moveOperatorToOwnLine(File $phpcsFile, int $operator, string $indent): void
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = ($operator - 1); $tokens[$i]['code'] === T_WHITESPACE; $i--) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        $hadTrailingWhitespace = false;

        for ($i = ($operator + 1); $tokens[$i]['code'] === T_WHITESPACE; $i++) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
            $hadTrailingWhitespace = true;
        }

        $phpcsFile->fixer
            ->addContentBefore($operator, $phpcsFile->eolChar . $indent);

        if ($hadTrailingWhitespace === true) {
            $phpcsFile->fixer
                ->addContent($operator, ' ');
        }
    }

    private function lineIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $first = $stackPtr;

        while (
            $first > 0
            && $tokens[$first - 1]['line'] === $tokens[$stackPtr]['line']
        ) {
            $first--;
        }

        if ($tokens[$first]['code'] !== T_WHITESPACE) {
            return '';
        }

        return str_replace(["\r", "\n"], '', $tokens[$first]['content']);
    }
}
