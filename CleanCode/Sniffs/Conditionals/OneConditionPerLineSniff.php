<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class OneConditionPerLineSniff implements Sniff
{
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE, T_FOR];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        // The span this sniff checks is defined once, in the support class, so
        // the sniffs that stand down inside it defer over the same bounds this
        // one walks. A for-loop's init and increment clauses lie outside it.
        $region = (new ConditionOperatorOwnership())->checkedRegion($phpcsFile, $stackPtr);

        if ($region === null) {
            return;
        }

        [$boundaryStart, $boundaryEnd] = $region;

        $regionStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($boundaryStart + 1), $boundaryEnd, true);

        if ($regionStart === false) {
            return;
        }

        $regionEnd = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($boundaryEnd - 1), $boundaryStart, true);

        $operators = (new ConditionOperatorOwnership())->findTopLevelTokens(
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

    // The block matched here is this signature against processMultiCondition's
    // below: `private function <name>(`, `File $phpcsFile,`, `int $stackPtr,`
    // and two more `int $<name>,` parameters. Both are parameter declarations,
    // not statements — there is no logic in either window, so there is nothing
    // to extract. The two methods take a similar list because they answer the
    // same caller about the same condition region, but what each does with it
    // is disjoint: one joins a split condition onto its keyword's line, the
    // other splits a joined one across lines and moves the boolean operators.
    // Merging them to silence this would put two opposite fixers behind one
    // branch, which is the design the split already rejected.
    // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
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

    // The other end of the processSingleCondition match, reported here because
    // the sniff names every participating block rather than only the later
    // one. This window covers $regionStart/$regionEnd, which this method reads
    // as the span to distribute across lines; the same-shaped parameters above
    // are $boundaryStart/$boundaryEnd, a different span (a for-header's
    // semicolons, not its condition). Identical parameter types carrying
    // different token offsets are not duplicated knowledge, and the sniff
    // cannot see the difference because it drops token content by design.
    // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
    private function processMultiCondition(
        File $phpcsFile,
        int $stackPtr,
        int $regionStart,
        int $regionEnd,
        array $operators
    ): void {
        $tokens = $phpcsFile->getTokens();
        $indent = $this->lineIndent($phpcsFile, $stackPtr) . '    ';

        if ($tokens[$regionStart]['line'] === $tokens[$regionEnd]['line']) {
            $fix = $phpcsFile->addFixableError(
                "Each condition of a multi-condition \"%s\" must be on its own line",
                $stackPtr,
                'MultipleConditionsOnOneLine',
                [strtolower($tokens[$stackPtr]['content'])]
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

        foreach ($operators as $operator) {
            $previous = $phpcsFile->findPrevious(T_WHITESPACE, ($operator - 1), null, true);

            if ($tokens[$previous]['line'] !== $tokens[$operator]['line']) {
                continue;
            }

            $fix = $phpcsFile->addFixableError(
                "Boolean operator \"%s\" must lead its condition line, not trail the previous one",
                $operator,
                'BooleanOperatorNotLeading',
                [$tokens[$operator]['content']]
            );

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
