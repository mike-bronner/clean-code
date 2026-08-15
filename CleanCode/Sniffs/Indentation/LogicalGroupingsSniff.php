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
 * lines directly inside a multi-line grouping are checked here, so a single-line
 * group never triggers a violation.
 *
 * Detection recognises groupings rather than excluding calls, so anything the
 * sniff does not positively identify as a grouping is left untouched: a
 * function, method, or constructor argument list, a `match` subject, a closure
 * or arrow-function parameter list, and any construct added to PHP later. Three
 * further shapes are held out of the measured conditions themselves — a comment
 * line inside a grouping, an arrow-function body used as a boolean operand, and
 * the continuation lines of a multi-line string, heredoc, or nowdoc. None of
 * these is a condition, so none is reported or reindented.
 *
 * All violations are auto-fixable — phpcbf reindents each offending condition
 * line to the correct nesting level.
 */
class LogicalGroupingsSniff implements Sniff
{
    private const INDENT = 4;

    /**
     * Every token that opens a region whose interior belongs to a nested
     * construct rather than to the grouping being measured, mapped to the
     * token-array key holding its closer.
     *
     * One list, read by both walks in this class, so a construct can never be
     * skipped by the walk that decides what a grouping is and missed by the
     * walk that measures one — the drift that let an arrow-function body be
     * reindented after `T_FN` was added to only the first of the two.
     *
     * An arrow function is here because its body — everything after `=>` — has
     * no bracket delimiter, so without the scope closer the body's tokens read
     * as though they belonged to the enclosing grouping.
     *
     * @var array<int|string, string>
     */
    private const NESTED_REGION_CLOSERS = [
        T_OPEN_PARENTHESIS => 'parenthesis_closer',
        T_OPEN_SHORT_ARRAY => 'bracket_closer',
        T_OPEN_SQUARE_BRACKET => 'bracket_closer',
        T_OPEN_CURLY_BRACKET => 'bracket_closer',
        T_FN => 'scope_closer',
    ];

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

            if ($this->opensGrouping($phpcsFile, $i) === false) {
                // Anything that is not a grouping is an opaque operand — a
                // call or construct argument list, a `match` subject, a
                // closure parameter list. Its contents count as a single
                // condition, so skip it whole.
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
     * True when the parenthesis opens a standalone grouping rather than an
     * argument or operand list belonging to whatever precedes it.
     *
     * The test is on the *grouping* side on purpose. Asking instead "is this a
     * call?" needs an allowlist of every token that can precede an argument
     * list — `T_STRING`, `T_MATCH`, `T_ANON_CLASS`, `T_CLOSURE`, `T_FN`,
     * `T_EXIT`, `T_ISSET`, each name token, … — and every name missing from
     * that list becomes a false positive on valid code. The grouping side is a
     * closed set instead: a grouping parenthesis can only appear where a new
     * operand may begin, which is directly after an operator, a `!`, a ternary
     * arm, an opening delimiter, or a `,`/`;` separator. Anything else —
     * including any token this sniff has never heard of — is therefore not a
     * grouping, so an unrecognised construct is left alone rather than
     * reindented.
     */
    private function opensGrouping(File $phpcsFile, int $parenPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($parenPtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        // Whole PHP_CodeSniffer token sets, never a hand-picked subset of one:
        // an operand may begin after any operator (arithmetic, comparison,
        // boolean, assignment, concatenation, cast, negation), after any
        // opening delimiter, after either separator, and after either ternary
        // arm. Taking each class entire is what stops the set from being
        // "complete except for the member nobody thought of".
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
     * If the token opens a nested region, returns the pointer to that region's
     * closer so the caller can jump past it in one step; otherwise returns the
     * pointer unchanged.
     *
     * Jumping rather than counting depth is what keeps both walks linear: a
     * group's walk touches only its own direct tokens, so n nested groups cost
     * n walks of their own contents instead of n overlapping rescans of the
     * whole condition.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipNested(array $tokens, int $i): int
    {
        $closerKey = self::NESTED_REGION_CLOSERS[$tokens[$i]['code']] ?? null;

        if ($closerKey === null) {
            return $i;
        }

        $closer = ($tokens[$i][$closerKey] ?? $i);

        // Both callers advance to whatever this returns, so a closer that did
        // not come after its opener would send the walk backwards and around
        // again. PHP_CodeSniffer does not produce one, which is exactly why
        // the walk must not depend on it never doing so.
        return ($closer > $i ? $closer : $i);
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
        $lines = [];
        $previousLine = $tokens[$groupOpen]['line'];
        $spannedThroughLine = 0;

        for ($i = ($groupOpen + 1); $i < $groupClose; $i++) {
            if (isset(Tokens::$emptyTokens[$tokens[$i]['code']]) === true) {
                // Whitespace and comment lines are not conditions: a comment
                // sitting inside a grouping must never be measured or reindented
                // as though it were a grouped condition.
                continue;
            }

            // PHP_CodeSniffer splits a multi-line string, heredoc, or nowdoc
            // into one token per physical line, and each of those tokens
            // begins a line. They are the interior of a single operand, not
            // conditions — and their leading whitespace is string content, so
            // reindenting one would rewrite the value. Tracking the last line
            // any token spans through marks them as continuations.
            $line = $tokens[$i]['line'];
            $isContinuation = $line <= $spannedThroughLine;
            $spannedThroughLine = max(
                $spannedThroughLine,
                $line + substr_count($tokens[$i]['content'], "\n")
            );

            if ($line !== $previousLine && $isContinuation === false) {
                $lines[] = $i;
            }

            $previousLine = $line;
            $skipTo = $this->skipNested($tokens, $i);

            if ($skipTo !== $i) {
                // The region's interior belongs to a nested construct, so it
                // is measured — if at all — by that construct's own group, not
                // this one. Landing on the closer also puts the next line-start
                // test against the line the region ended on.
                $previousLine = $tokens[$skipTo]['line'];
                $i = $skipTo;

                continue;
            }

            if (isset(self::NESTED_REGION_CLOSERS[$tokens[$i]['code']]) === true) {
                // A region opener whose closer PHP_CodeSniffer never recorded
                // (unbalanced or unparsable source) leaves the walk unable to
                // tell nested tokens from this group's own. Stop measuring
                // rather than report or reindent a line that may be neither.
                return $lines;
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
