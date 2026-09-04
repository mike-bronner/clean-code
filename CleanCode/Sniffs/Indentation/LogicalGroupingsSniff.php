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

    public function process(File $phpcsFile, $stackPtr): void
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
                    // A nested region with no usable end: from here the walk
                    // cannot tell the condition's own parentheses from a nested
                    // construct's, so it collects no more rather than classify
                    // on a guess.
                    return $groups;
                }

                // A token that opens no region at all comes back unchanged, so
                // this both jumps a region and advances past anything else.
                $i = $skipTo;

                continue;
            }

            if (isset($tokens[$i]['parenthesis_closer']) === false) {
                // Same reasoning, for the parenthesis family itself.
                return $groups;
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

    private function hasTopLevelBoolean(File $phpcsFile, int $open, int $close): bool
    {
        $tokens = $phpcsFile->getTokens();
        $booleans = array_keys(Tokens::$booleanOperators);

        for ($i = ($open + 1); $i < $close; $i++) {
            $this->cacheCounts['conditionWalk.steps']++;
            $skipTo = $this->skipNested($tokens, $i);

            if ($skipTo === null) {
                // A nested region with no usable end. Every boolean past this
                // point may belong to that region rather than to these
                // parentheses, so the honest answer is that no top-level
                // boolean was found: the parenthesis is left unclassified, and
                // nothing inside it is measured or reindented.
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

        // A closer PHP_CodeSniffer never recorded (unbalanced or unparsable
        // source), or one recorded before its own opener: either way the region
        // has no usable end. Callers advance to whatever this returns, so the
        // second case would send a walk backwards and around again. PHP_CodeSniffer
        // does not produce one, which is exactly why the walk must not depend on
        // it never doing so.
        if (
            $closer === null
            || $closer <= $i
        ) {
            return null;
        }

        // One step, whatever the region holds. Walking it instead costs one per
        // token in it, which is what makes n nested regions quadratic.
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
            // Mid-line spacing, never a line's indentation: the condition is
            // on the opening parenthesis's line, so anything directly before
            // it came after that parenthesis.
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

            if (
                $line !== $previousLine
                && $isContinuation === false
            ) {
                $lines[] = $i;
            }

            $previousLine = $line;
            $skipTo = $this->skipNested($tokens, $i);

            if ($skipTo === null) {
                // A nested region with no usable end leaves the walk unable to
                // tell nested tokens from this group's own. Stop measuring
                // rather than report or reindent a line that may be neither.
                return $lines;
            }

            if ($skipTo !== $i) {
                // The region's interior belongs to a nested construct, so it
                // is measured — if at all — by that construct's own group, not
                // this one. Landing on the closer also puts the next line-start
                // test against the line the region ended on.
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

        // One token examined: the line's first is recorded, not walked back to.
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
            // Token lines never decrease, so the first pointer seen for a
            // line is the same one the backward walk used to land on.
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

        // Guarded, and nothing here can tell the guard from its absence —
        // measured, not assumed, and pinned by the test in
        // tests/Standards/LogicalGroupingsTest.php that says so in its own
        // docblock. PHPCS ends a whitespace token at its newline, so the
        // first token of a line that carries code is that line's indentation
        // and nothing else (a blank line's token is the newline alone, and
        // this method is only ever pointed at a line holding a condition);
        // stripping it, rtrim()ing it, and concatenating a failed read's null
        // all leave the same empty prefix. The fallback is written
        // for the shape the docblock above promises to preserve — a token that
        // does carry a leading break — so a tokenizer that ever hands one over
        // finds the failure already answered. `/[^\r\n]+$/` quantifies one
        // character class against an anchor and carries no `/u` modifier, so
        // nothing is known to reach it in the first place.
        $eol = preg_replace('/[^\r\n]+$/', '', $existing) ?? rtrim($existing, " \t\x0B\f");
        $phpcsFile->fixer
            ->replaceToken($first, $eol . $padding);
    }
}
