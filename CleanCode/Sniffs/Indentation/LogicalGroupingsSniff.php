<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Indentation;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Indentation: Logical Groupings" standard.
 *
 * When an if/elseif/while/for/do-while condition contains a parenthesized
 * sub-grouping of boolean conditions — e.g. `($a && $b)` — and that grouping
 * is broken across multiple lines, the conditions inside the parentheses must
 * be indented one level (four spaces) deeper than the line the group opens on,
 * so the logical structure is visible at a glance. Every condition within the
 * same group aligns to that one level, and a group nested inside another group
 * indents one further level than its parent — validated to arbitrary depth.
 *
 * Simple multi-line conditions with no parenthesized sub-grouping are left
 * entirely alone: their one-condition-per-line layout is the concern of the
 * "Conditionals: One Condition Per Line" standard (#17), not this one. Only the
 * lines directly inside a multi-line grouping are checked here, so a
 * function-call argument list or a single-line group never triggers a
 * violation. Constructs that are not condition operands are treated as opaque
 * — a comment line inside a grouping and an arrow-function body used as a
 * boolean operand are never measured as conditions, so neither is a false
 * positive.
 *
 * All violations are auto-fixable — phpcbf reindents each offending condition
 * line to the correct nesting level.
 */
class LogicalGroupingsSniff implements Sniff
{
    private const INDENT = 4;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE, T_FOR];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
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

    /**
     * Collects, in source order, every parenthesis inside the control
     * structure's condition that opens a boolean-condition sub-grouping — a
     * parenthesis that is not a function/construct call and that contains at
     * least one top-level boolean operator. Nested groupings are included, so
     * each is later checked against its own enclosing level.
     *
     * @return array<int>
     */
    private function groupingParentheses(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $groups = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            if (isset($tokens[$i]['parenthesis_closer']) === false) {
                continue;
            }

            if ($this->isCall($phpcsFile, $i) === true) {
                // A call's argument list is not a condition grouping; its
                // contents count as a single condition, so skip it whole.
                $i = $tokens[$i]['parenthesis_closer'];

                continue;
            }

            if ($this->hasTopLevelBoolean($phpcsFile, $i, $tokens[$i]['parenthesis_closer']) === true) {
                $groups[] = $i;
            }
        }

        return $groups;
    }

    /**
     * True when the parenthesis is the argument list of a function, method, or
     * language-construct call rather than a standalone grouping — determined by
     * the token immediately preceding it.
     */
    private function isCall(File $phpcsFile, int $parenPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($parenPtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        $callPreceders = [
            T_STRING,
            T_VARIABLE,
            T_CLOSE_PARENTHESIS,
            T_CLOSE_SQUARE_BRACKET,
            T_CLOSE_CURLY_BRACKET,
            T_NAME_QUALIFIED,
            T_NAME_FULLY_QUALIFIED,
            T_NAME_RELATIVE,
            T_ARRAY,
            T_ISSET,
            T_EMPTY,
            T_LIST,
            T_EVAL,
            T_STATIC,
            T_SELF,
            T_PARENT,
            T_ANON_CLASS,
        ];

        return in_array($tokens[$previous]['code'], $callPreceders, true);
    }

    /**
     * True when the region between the parentheses contains a boolean operator
     * (`&&`, `||`, `and`, `or`, `xor`) that is not itself nested inside a
     * deeper set of parentheses, brackets, or braces.
     */
    private function hasTopLevelBoolean(File $phpcsFile, int $open, int $close): bool
    {
        $tokens = $phpcsFile->getTokens();
        $booleans = array_keys(Tokens::$booleanOperators);

        for ($i = ($open + 1); $i < $close; $i++) {
            $skipTo = $this->skipNested($tokens, $i);

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

    /**
     * If the token opens a nested paren/bracket/brace — or is an arrow function
     * whose body has no bracket delimiter — returns its matching closer (or
     * scope closer) so the caller can jump past the nested region; otherwise
     * returns the pointer unchanged.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipNested(array $tokens, int $i): int
    {
        if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer'])) {
            return $tokens[$i]['parenthesis_closer'];
        }

        $bracketed = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];

        if (in_array($tokens[$i]['code'], $bracketed, true) === true && isset($tokens[$i]['bracket_closer'])) {
            return $tokens[$i]['bracket_closer'];
        }

        if ($tokens[$i]['code'] === T_FN && isset($tokens[$i]['scope_closer']) === true) {
            // An arrow function's body (everything after `=>`) has no bracket
            // delimiter, so a boolean operator inside it would otherwise be
            // read as belonging to the enclosing grouping. The whole `fn` is a
            // single operand — skip past its body to its scope closer.
            return $tokens[$i]['scope_closer'];
        }

        return $i;
    }

    /**
     * Checks one parenthesized grouping: when its parentheses span multiple
     * lines, the first condition inside must sit one level deeper than the
     * line the group opens on, and every subsequent condition must align with
     * it. Each offending condition line is reported and fixed independently.
     */
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
            $actual = $tokens[$pointer]['column'] - 1;

            if ($actual === $expected) {
                continue;
            }

            if ($index === 0) {
                $error = 'Grouped condition must be indented one level deeper than its'
                    . ' enclosing condition; expected %s spaces, found %s';
                $code = 'GroupNotIndented';
            } else {
                $error = "Condition in a parenthesized group must align with the group's"
                    . ' first condition; expected %s spaces, found %s';
                $code = 'MisalignedGroupedCondition';
            }

            $fix = $phpcsFile->addFixableError($error, $pointer, $code, [$expected, $actual]);

            if ($fix === true) {
                $this->reindent($phpcsFile, $pointer, $expected);
            }
        }
    }

    /**
     * The pointers to the first token on each line that holds a condition
     * directly inside the grouping — those at nesting depth zero relative to
     * the group, skipping lines that belong to a deeper nested grouping and
     * lines that only close a bracket.
     *
     * @return array<int, int>
     */
    private function directConditionLines(File $phpcsFile, int $groupOpen, int $groupClose): array
    {
        $tokens = $phpcsFile->getTokens();
        $openers = [T_OPEN_PARENTHESIS, T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];
        $closers = [T_CLOSE_PARENTHESIS, T_CLOSE_SHORT_ARRAY, T_CLOSE_SQUARE_BRACKET, T_CLOSE_CURLY_BRACKET];
        $lines = [];
        $depth = 0;

        for ($i = ($groupOpen + 1); $i < $groupClose; $i++) {
            if (isset(Tokens::$emptyTokens[$tokens[$i]['code']]) === true) {
                // Whitespace and comment lines are not conditions: a comment
                // sitting inside a grouping must never be measured or reindented
                // as though it were a grouped condition.
                continue;
            }

            $isCloser = in_array($tokens[$i]['code'], $closers, true);

            if ($isCloser === true) {
                $depth--;
            }

            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($i - 1), null, true);
            $isLineStart = $previous === false || $tokens[$previous]['line'] !== $tokens[$i]['line'];

            if ($isLineStart === true && $depth === 0 && $isCloser === false) {
                $lines[] = $i;
            }

            if (in_array($tokens[$i]['code'], $openers, true) === true) {
                $depth++;
            }
        }

        return $lines;
    }

    /**
     * The indentation (leading-space count) of the line the given token sits on.
     */
    private function indentOfLine(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$stackPtr]['line'];
        $first = $stackPtr;

        while ($first > 0 && $tokens[($first - 1)]['line'] === $line) {
            $first--;
        }

        for ($i = $first; $tokens[$i]['line'] === $line; $i++) {
            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                return $tokens[$i]['column'] - 1;
            }
        }

        return 0;
    }

    /**
     * Rewrites the leading whitespace of the given token's line to the expected
     * number of spaces, preserving any leading end-of-line characters.
     */
    private function reindent(File $phpcsFile, int $pointer, int $expected): void
    {
        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$pointer]['line'];
        $first = $pointer;

        while ($first > 0 && $tokens[($first - 1)]['line'] === $line) {
            $first--;
        }

        $padding = str_repeat(' ', $expected);

        if ($tokens[$first]['code'] !== T_WHITESPACE) {
            $phpcsFile->fixer->addContentBefore($first, $padding);

            return;
        }

        $eol = preg_replace('/[^\r\n]+$/', '', $tokens[$first]['content']);
        $phpcsFile->fixer->replaceToken($first, $eol . $padding);
    }
}
