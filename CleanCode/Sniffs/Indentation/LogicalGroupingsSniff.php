<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Indentation;

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class LogicalGroupingsSniff implements Sniff
{
    private const INDENT = 4;

    private const NESTED_REGION_CLOSERS = [
        T_OPEN_PARENTHESIS => 'parenthesis_closer',
        T_OPEN_SHORT_ARRAY => 'bracket_closer',
        T_OPEN_SQUARE_BRACKET => 'bracket_closer',
        T_OPEN_CURLY_BRACKET => 'bracket_closer',
        T_FN => 'scope_closer',
    ];

    private array $lineStarts = [];

    private ?string $lineStartsKey = null;

    private array $cacheCounts = [
        'lineStarts.builds' => 0,
        'lineStarts.hits' => 0,
        'conditionWalk.steps' => 0,
        'conditionWalk.jumps' => 0,
        'lineStarts.steps' => 0,
    ];

    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE, T_FOR];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]['parenthesis_opener']) === false
            || isset($tokens[$stackPtr]['parenthesis_closer']) === false
        ) {
            return;
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        foreach ($this->groupingParentheses($phpcsFile, $opener, $closer) as $groupOpen) {
            $this->checkGroup($phpcsFile, $groupOpen);
        }
    }

    private function groupingParentheses(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $groups = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            $this->cacheCounts['conditionWalk.steps']++;

            if ($tokens[$i]['code'] !== T_OPEN_PARENTHESIS) {
                $skipTo = $this->skipNested($tokens, $i);

                if ($skipTo === null) {
                    return $groups;
                }

                $i = $skipTo;

                continue;
            }

            if (isset($tokens[$i]['parenthesis_closer']) === false) {
                return $groups;
            }

            if ($this->opensGrouping($phpcsFile, $i) === false) {
                $i = $tokens[$i]['parenthesis_closer'];

                continue;
            }

            if ($this->hasTopLevelBoolean($phpcsFile, $i, $tokens[$i]['parenthesis_closer']) === true) {
                $groups[] = $i;
            }
        }

        return $groups;
    }

    private function opensGrouping(File $phpcsFile, int $parenPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($parenPtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        $expressionStarters = Tokens::$booleanOperators
            + Tokens::$comparisonTokens
            + Tokens::$operators
            + Tokens::$castTokens
            + Tokens::$assignmentTokens
            + [
                T_STRING_CONCAT => T_STRING_CONCAT,
                T_BOOLEAN_NOT => T_BOOLEAN_NOT,
                T_OPEN_PARENTHESIS => T_OPEN_PARENTHESIS,
                T_OPEN_SHORT_ARRAY => T_OPEN_SHORT_ARRAY,
                T_OPEN_SQUARE_BRACKET => T_OPEN_SQUARE_BRACKET,
                T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET,
                T_SEMICOLON => T_SEMICOLON,
                T_COMMA => T_COMMA,
                T_INLINE_THEN => T_INLINE_THEN,
                T_INLINE_ELSE => T_INLINE_ELSE,
            ];

        return isset($expressionStarters[$tokens[$previous]['code']]);
    }

    private function hasTopLevelBoolean(File $phpcsFile, int $open, int $close): bool
    {
        $tokens = $phpcsFile->getTokens();
        $booleans = array_keys(Tokens::$booleanOperators);

        for ($i = ($open + 1); $i < $close; $i++) {
            $this->cacheCounts['conditionWalk.steps']++;
            $skipTo = $this->skipNested($tokens, $i);

            if ($skipTo === null) {
                return false;
            }

            if ($skipTo !== $i) {
                $i = $skipTo;

                continue;
            }

            if (in_array($tokens[$i]['code'], $booleans, true) === true) {
                return true;
            }
        }

        return false;
    }

    private function skipNested(array $tokens, int $i): ?int
    {
        $closerKey = self::NESTED_REGION_CLOSERS[$tokens[$i]['code']] ?? null;

        if ($closerKey === null) {
            return $i;
        }

        $closer = ($tokens[$i][$closerKey] ?? null);

        if (
            $closer === null
            || $closer <= $i
        ) {
            return null;
        }

        $this->cacheCounts['conditionWalk.jumps']++;

        return $closer;
    }

    private function checkGroup(File $phpcsFile, int $groupOpen): void
    {
        $tokens = $phpcsFile->getTokens();
        $groupClose = $tokens[$groupOpen]['parenthesis_closer'];

        if ($tokens[$groupOpen]['line'] === $tokens[$groupClose]['line']) {
            return;
        }

        $expected = $this->indentOfLine($phpcsFile, $groupOpen) + self::INDENT;
        $conditionLines = $this->directConditionLines($phpcsFile, $groupOpen, $groupClose);

        foreach ($conditionLines as $index => $pointer) {
            if ($tokens[$pointer]['line'] === $tokens[$groupOpen]['line']) {
                $this->checkGluedFirstCondition($phpcsFile, $pointer, $expected);

                continue;
            }

            $actual = $tokens[$pointer]['column'] - 1;

            if ($actual === $expected) {
                continue;
            }

            [$error, $code] = $this->groupIndentReport($index);
            $fix = $phpcsFile->addFixableError($error, $pointer, $code, [$expected, $actual]);

            if ($fix === true) {
                $this->reindent($phpcsFile, $pointer, $expected);
            }
        }
    }

    private function groupIndentReport(int $index): array
    {
        if ($index === 0) {
            return [
                'Grouped condition must be indented one level deeper than its'
                    . ' enclosing condition; expected %s spaces, found %s',
                'GroupNotIndented',
            ];
        }

        return [
            "Condition in a parenthesized group must align with the group's"
                . ' first condition; expected %s spaces, found %s',
            'MisalignedGroupedCondition',
        ];
    }

    private function checkGluedFirstCondition(File $phpcsFile, int $pointer, int $expected): void
    {
        $error = 'The first condition of a parenthesized group must start on its own'
            . ' line, indented one level deeper than its enclosing condition;'
            . ' expected %s spaces';
        $fix = $phpcsFile->addFixableError($error, $pointer, 'GroupNotIndented', [$expected]);

        if ($fix === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $break = $phpcsFile->eolChar . str_repeat(' ', $expected);

        if ($tokens[($pointer - 1)]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer
                ->replaceToken(($pointer - 1), $break);

            return;
        }

        $phpcsFile->fixer
            ->addContentBefore($pointer, $break);
    }

    private function directConditionLines(File $phpcsFile, int $groupOpen, int $groupClose): array
    {
        $tokens = $phpcsFile->getTokens();
        $lines = [];
        $previousLine = 0;
        $spannedThroughLine = 0;

        for ($i = ($groupOpen + 1); $i < $groupClose; $i++) {
            $this->cacheCounts['conditionWalk.steps']++;

            if (isset(Tokens::$emptyTokens[$tokens[$i]['code']]) === true) {
                continue;
            }

            $line = $tokens[$i]['line'];
            $isContinuation = $line <= $spannedThroughLine;
            $spannedThroughLine = max(
                $spannedThroughLine,
                $line + substr_count($tokens[$i]['content'], "\n")
            );

            if (
                $line !== $previousLine
                && $isContinuation === false
            ) {
                $lines[] = $i;
            }

            $previousLine = $line;
            $skipTo = $this->skipNested($tokens, $i);

            if ($skipTo === null) {
                return $lines;
            }

            if ($skipTo !== $i) {
                $previousLine = $tokens[$skipTo]['line'];
                $i = $skipTo;

                continue;
            }
        }

        return $lines;
    }

    private function lineStart(File $phpcsFile, int $stackPtr): int
    {
        $this->indexLineStarts($phpcsFile);

        return ($this->lineStarts[$this->step($phpcsFile, $stackPtr)['line']] ?? $stackPtr);
    }

    private function indexLineStarts(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;

        $key = $tokenStreams->key($phpcsFile);

        if ($this->lineStartsKey === $key) {
            $this->cacheCounts['lineStarts.hits']++;

            return;
        }

        $this->cacheCounts['lineStarts.builds']++;
        $this->lineStartsKey = $key;
        $this->lineStarts = [];

        foreach ($phpcsFile->getTokens() as $pointer => $token) {
            $this->lineStarts[$token['line']] ??= $pointer;
        }
    }

    private function step(File $phpcsFile, int $pointer): array
    {
        $this->cacheCounts['lineStarts.steps']++;

        return $phpcsFile->getTokens()[$pointer];
    }

    private function indentOfLine(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$stackPtr]['line'];

        for (
            $i = $this->lineStart($phpcsFile, $stackPtr);
            isset($tokens[$i]) === true
            && $tokens[$i]['line'] === $line;
            $i++
        ) {
            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                return $tokens[$i]['column'] - 1;
            }
        }

        return 0;
    }

    private function reindent(File $phpcsFile, int $pointer, int $expected): void
    {
        $tokens = $phpcsFile->getTokens();
        $first = $this->lineStart($phpcsFile, $pointer);
        $padding = str_repeat(' ', $expected);

        if ($tokens[$first]['code'] !== T_WHITESPACE) {
            $phpcsFile->fixer
                ->addContentBefore($first, $padding);

            return;
        }

        $existing = $tokens[$first]['content'];

        $eol = preg_replace('/[^\r\n]+$/', '', $existing) ?? rtrim($existing, " \t\x0B\f");
        $phpcsFile->fixer
            ->replaceToken($first, $eol . $padding);
    }
}
