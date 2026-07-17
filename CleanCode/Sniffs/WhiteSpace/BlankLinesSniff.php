<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the Blank Lines clean-code standard: blank lines only separate
 * concepts — at most one in a row, and never directly inside the braces of
 * classes, interfaces, traits, enums, functions, closures, or methods.
 *
 * All violations are auto-fixable: superfluous blank lines are removed,
 * leaving at most a single blank line between concepts and none at the
 * start or end of a brace-delimited body.
 */
class BlankLinesSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * @param int $stackPtr
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $blankLines = $this->findBlankLines($phpcsFile);

        if ($blankLines !== []) {
            $firstTokenOnLine = $this->mapFirstTokenPerLine($phpcsFile);
            $handledLines = $this->checkBraces($phpcsFile, $blankLines, $firstTokenOnLine);
            $this->checkConsecutiveBlankLines($phpcsFile, $blankLines, $firstTokenOnLine, $handledLines);
        }

        return $phpcsFile->numTokens;
    }

    /**
     * A line is blank when every token on it is plain whitespace. Multi-line
     * tokens (heredocs, multi-line strings) mark every line they span as
     * non-blank, so their interior never gets touched. A trailing newline
     * terminates a token's last line without putting content on the next one
     * (e.g. the open tag `<?php\n` spans only its own line), so it never
     * claims the following line.
     *
     * @return array<int, true> line number => true, ascending
     */
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

            if ($spannedLines > 0 && str_ends_with($token['content'], "\n") === true) {
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

    /**
     * @return array<int, int> line number => pointer of the first token on it
     */
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

    /**
     * Flags blank lines directly after the opening brace or before the
     * closing brace of OO structures, functions, and closures.
     *
     * @param array<int, true> $blankLines
     * @param array<int, int> $firstTokenOnLine
     *
     * @return array<int, true> blank lines consumed by brace violations
     */
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

            if (
                ($openerLine + 1) < $closerLine
                && isset($blankLines[$openerLine + 1]) === true
                && isset($handledLines[$openerLine + 1]) === false
            ) {
                $run = $this->collectRun($blankLines, $openerLine + 1, +1, $closerLine - 1);
                $handledLines += $run;
                $this->addBlankLinesError(
                    $phpcsFile,
                    $firstTokenOnLine,
                    min(array_keys($run)),
                    array_keys($run),
                    'Expected no blank lines after an opening brace; found %s',
                    'AfterOpeningBrace'
                );
            }

            if (
                ($closerLine - 1) > $openerLine
                && isset($blankLines[$closerLine - 1]) === true
                && isset($handledLines[$closerLine - 1]) === false
            ) {
                $run = $this->collectRun($blankLines, $closerLine - 1, -1, $openerLine + 1);
                $handledLines += $run;
                $this->addBlankLinesError(
                    $phpcsFile,
                    $firstTokenOnLine,
                    min(array_keys($run)),
                    array_keys($run),
                    'Expected no blank lines before a closing brace; found %s',
                    'BeforeClosingBrace'
                );
            }
        }

        return $handledLines;
    }

    /**
     * Flags runs of two or more consecutive blank lines anywhere in the file
     * that were not already consumed by a brace violation.
     *
     * @param array<int, true> $blankLines
     * @param array<int, int> $firstTokenOnLine
     * @param array<int, true> $handledLines
     */
    private function checkConsecutiveBlankLines(
        File $phpcsFile,
        array $blankLines,
        array $firstTokenOnLine,
        array $handledLines
    ): void {
        $runStart = null;
        $previous = null;

        foreach (array_keys($blankLines) as $line) {
            if ($previous !== null && $line === ($previous + 1)) {
                $previous = $line;
                continue;
            }

            $this->reportConsecutiveRun($phpcsFile, $firstTokenOnLine, $handledLines, $runStart, $previous);
            $runStart = $line;
            $previous = $line;
        }

        $this->reportConsecutiveRun($phpcsFile, $firstTokenOnLine, $handledLines, $runStart, $previous);
    }

    /**
     * @param array<int, int> $firstTokenOnLine
     * @param array<int, true> $handledLines
     */
    private function reportConsecutiveRun(
        File $phpcsFile,
        array $firstTokenOnLine,
        array $handledLines,
        ?int $runStart,
        ?int $runEnd
    ): void {
        if ($runStart === null || $runEnd === $runStart || isset($handledLines[$runStart]) === true) {
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

    /**
     * Collects the unbroken run of blank lines starting at $start, walking in
     * $direction (+1 down, -1 up), without passing $limit.
     *
     * @param array<int, true> $blankLines
     *
     * @return array<int, true> line number => true
     */
    private function collectRun(array $blankLines, int $start, int $direction, int $limit): array
    {
        $run = [];

        for ($line = $start; isset($blankLines[$line]) === true; $line += $direction) {
            if (($direction > 0 && $line > $limit) || ($direction < 0 && $line < $limit)) {
                break;
            }

            $run[$line] = true;
        }

        return $run;
    }

    /**
     * Reports a fixable error at $reportLine; the fix removes the whitespace
     * of every line in $linesToRemove.
     *
     * @param array<int, int> $firstTokenOnLine
     * @param array<int> $linesToRemove
     */
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
        $phpcsFile->fixer->beginChangeset();

        foreach ($linesToRemove as $line) {
            $pointer = $firstTokenOnLine[$line];

            while (isset($tokens[$pointer]) === true && $tokens[$pointer]['line'] === $line) {
                $phpcsFile->fixer->replaceToken($pointer, '');
                $pointer++;
            }
        }

        $phpcsFile->fixer->endChangeset();
    }
}
