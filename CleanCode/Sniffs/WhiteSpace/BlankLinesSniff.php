<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class BlankLinesSniff implements Sniff
{
    private const MESSAGE_OPENER = 'Expected no blank lines after an opening brace; found %s';

    private const MESSAGE_CLOSER = 'Expected no blank lines before a closing brace; found %s';

    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    public function process(File $phpcsFile, $stackPtr): int
    {
        $blankLines = $this->findBlankLines($phpcsFile);

        if ($blankLines !== []) {
            $firstTokenOnLine = $this->mapFirstTokenPerLine($phpcsFile);
            $handledLines = $this->checkBraces($phpcsFile, $blankLines, $firstTokenOnLine);
            $this->checkConsecutiveBlankLines($phpcsFile, $blankLines, $firstTokenOnLine, $handledLines);
        }

        return $phpcsFile->numTokens;
    }

    private function findBlankLines(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $lastLine = $tokens[$phpcsFile->numTokens - 1]['line'];
        $nonBlankLines = [];

        foreach ($tokens as $token) {
            if ($token['code'] === T_WHITESPACE) {
                continue;
            }

            $spannedLines = substr_count($token['content'], "\n");

            if (
                $spannedLines > 0
                && str_ends_with($token['content'], "\n") === true
            ) {
                $spannedLines--;
            }

            for ($line = $token['line']; $line <= ($token['line'] + $spannedLines); $line++) {
                $nonBlankLines[$line] = true;
            }
        }

        $blankLines = [];

        for ($line = 1; $line <= $lastLine; $line++) {
            if (isset($nonBlankLines[$line]) === false) {
                $blankLines[$line] = true;
            }
        }

        return $blankLines;
    }

    private function mapFirstTokenPerLine(File $phpcsFile): array
    {
        $firstTokenOnLine = [];

        foreach ($phpcsFile->getTokens() as $pointer => $token) {
            if (isset($firstTokenOnLine[$token['line']]) === false) {
                $firstTokenOnLine[$token['line']] = $pointer;
            }
        }

        return $firstTokenOnLine;
    }

    private function checkBraces(File $phpcsFile, array $blankLines, array $firstTokenOnLine): array
    {
        $tokens = $phpcsFile->getTokens();
        $scopeTokens = Tokens::$ooScopeTokens + [T_FUNCTION => T_FUNCTION, T_CLOSURE => T_CLOSURE];
        $handledLines = [];

        for ($pointer = 0; $pointer < $phpcsFile->numTokens; $pointer++) {
            if (
                isset($scopeTokens[$tokens[$pointer]['code']]) === false
                || isset($tokens[$pointer]['scope_opener']) === false
            ) {
                continue;
            }

            $openerLine = $tokens[$tokens[$pointer]['scope_opener']]['line'];
            $closerLine = $tokens[$tokens[$pointer]['scope_closer']]['line'];

            $first = $openerLine + 1;
            $last = $closerLine - 1;

            if ($first > $last) {
                continue;
            }

            // The two brace edges differ only in where the run starts, which
            // way it grows, and what it is called — the walk, the
            // already-handled guard and the report are one piece of knowledge,
            // so they are written once and driven from this table. The closing
            // edge is read after the opening edge has folded its own run into
            // $handledLines, so a run that reaches both braces is reported
            // once, against the opening one.
            $edges = [
                [$first, +1, $last, self::MESSAGE_OPENER, 'AfterOpeningBrace'],
                [$last, -1, $first, self::MESSAGE_CLOSER, 'BeforeClosingBrace'],
            ];

            foreach ($edges as [$startLine, $direction, $limit, $message, $code]) {
                if (
                    isset($blankLines[$startLine]) === false
                    || isset($handledLines[$startLine]) === true
                ) {
                    continue;
                }

                $run = $this->collectRun($blankLines, $startLine, $direction, $limit);
                $handledLines += $run;
                $this->addBlankLinesError(
                    $phpcsFile,
                    $firstTokenOnLine,
                    min(array_keys($run)),
                    array_keys($run),
                    $message,
                    $code
                );
            }
        }

        // The block matched here is not a statement run: it is this method's
        // `return`, then — comment lines being dropped before comparison —
        // checkConsecutiveBlankLines's parameter list below. Its twin is
        // collectRun's `return` followed by addBlankLinesError's parameter
        // list. The window spans a method boundary in both places, so it pairs
        // the tail of one responsibility with the declaration of an unrelated
        // next one. What the four methods do shares nothing: two report, one
        // walks a run of lines, one maps them. There is no common body here to
        // lift out, only the coincidence that a `return` precedes a wrapped
        // signature twice in a file whose methods are ordered by call depth.
        // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
        return $handledLines;
    }

    private function checkConsecutiveBlankLines(
        File $phpcsFile,
        array $blankLines,
        array $firstTokenOnLine,
        array $handledLines
    ): void {
        $runStart = null;
        $previous = null;

        foreach (array_keys($blankLines) as $line) {
            if (
                $previous !== null
                && $line === ($previous + 1)
            ) {
                $previous = $line;
                continue;
            }

            $this->reportConsecutiveRun($phpcsFile, $firstTokenOnLine, $handledLines, $runStart, $previous);
            $runStart = $line;
            $previous = $line;
        }

        $this->reportConsecutiveRun($phpcsFile, $firstTokenOnLine, $handledLines, $runStart, $previous);
    }

    private function reportConsecutiveRun(
        File $phpcsFile,
        array $firstTokenOnLine,
        array $handledLines,
        ?int $runStart,
        ?int $runEnd
    ): void {
        if (
            $runStart === null
            || $runEnd === $runStart
            || isset($handledLines[$runStart]) === true
        ) {
            return;
        }

        $this->addBlankLinesError(
            $phpcsFile,
            $firstTokenOnLine,
            $runStart + 1,
            range($runStart + 1, $runEnd),
            'Expected at most one consecutive blank line; found %s',
            'ConsecutiveBlankLines',
            ($runEnd - $runStart) + 1
        );
    }

    private function collectRun(array $blankLines, int $start, int $direction, int $limit): array
    {
        $run = [];

        for ($line = $start; isset($blankLines[$line]) === true; $line += $direction) {
            if (
                ($direction > 0 && $line > $limit)
                || ($direction < 0 && $line < $limit)
            ) {
                break;
            }

            $run[$line] = true;
        }

        // The other end of the checkBraces match above; the sniff reports each
        // participating block at its own first line. This `return` hands back
        // the run of blank lines just walked, a value the caller folds into
        // $handledLines; the matching `return` above hands back that
        // accumulator itself. Same token shape, opposite direction of data
        // flow — and the parameter list that follows belongs to the reporting
        // method, which this one never calls. Nothing is shared to extract.
        // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
        return $run;
    }

    private function addBlankLinesError(
        File $phpcsFile,
        array $firstTokenOnLine,
        int $reportLine,
        array $linesToRemove,
        string $message,
        string $code,
        ?int $foundCount = null
    ): void {
        $fix = $phpcsFile->addFixableError(
            $message,
            $firstTokenOnLine[$reportLine],
            $code,
            [$foundCount ?? count($linesToRemove)]
        );

        if ($fix === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $phpcsFile->fixer
            ->beginChangeset();

        foreach ($linesToRemove as $line) {
            $pointer = $firstTokenOnLine[$line];

            while (
                isset($tokens[$pointer]) === true
                && $tokens[$pointer]['line'] === $line
            ) {
                $phpcsFile->fixer
                    ->replaceToken($pointer, '');
                $pointer++;
            }
        }

        $phpcsFile->fixer
            ->endChangeset();
    }
}
