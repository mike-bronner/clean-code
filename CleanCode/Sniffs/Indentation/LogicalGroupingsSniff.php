<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Indentation;

use MikeBronner\CleanCode\Helpers\TokenStreams;
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
 * A first condition written on the group's own opening line is held to the
 * same level, which it can only reach on a line of its own: the whitespace in
 * front of it is mid-line spacing, and the indentation of the line it shares
 * belongs to the enclosing condition.
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
 * Nor is anything nested below the condition. An array literal, a subscript, a
 * brace block, and an arrow-function body are each jumped whole wherever the
 * sniff walks, so a parenthesis inside one of them — an array value or element,
 * a statement in a closure body — is never mistaken for a grouping of the
 * condition that encloses it. Where such a region has no recorded end, which
 * only happens on source PHP cannot parse, every walk stops rather than read
 * its interior as the condition's own tokens: nothing is reported and nothing
 * is reindented.
 *
 * All violations are auto-fixable — phpcbf reindents each offending condition
 * line to the correct nesting level, and gives a condition glued to its
 * group's opening parenthesis a line of its own at that level.
 */
class LogicalGroupingsSniff implements Sniff
{
    private const INDENT = 4;

    /**
     * Every token that opens a region whose interior belongs to a nested
     * construct rather than to the grouping being measured, mapped to the
     * token-array key holding its closer.
     *
     * One list, read by all three walks in this class — the one collecting
     * groupings, the one testing a grouping for a top-level boolean, and the
     * one measuring a grouping's conditions — so a construct can never be
     * opaque to one walk and transparent to another. That drift is what let an
     * arrow-function body be reindented when `T_FN` reached only one list, and
     * what let an array literal's contents be read as conditions when only two
     * of the three walks stepped over brackets.
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
     * Physical line number => pointer to the first token recorded on that line,
     * for the token stream named by $lineStartsKey.
     *
     * @var array<int, int>
     */
    private array $lineStarts = [];

    /**
     * The token stream $lineStarts describes, as TokenStreams::key() builds it.
     */
    private ?string $lineStartsKey = null;

    /**
     * How many times $lineStarts was built, and how many times the key guard
     * answered a read from the index already built.
     *
     * The index exists to absorb many reads per token stream into one pass, and
     * nothing a black-box test can observe tells "built once, read n times"
     * from "rebuilt on every read": both report the same violations. These two
     * counters are what tell them apart, and
     * tests/Standards/LogicalGroupingsTest.php pins both numbers.
     *
     * Each increment sits inside the same branch as the guard it counts, so a
     * guard that stopped working cannot leave the counts intact. The totals are
     * cumulative for the life of the sniff instance — tests/Helpers.php's
     * buildRuleset() memoises the instance, so every test in one file shares
     * one — and are read as a delta around a single process() run.
     *
     * @var array<string, int>
     */
    private array $cacheCounts = [
        'lineStarts.builds' => 0,
        'lineStarts.hits' => 0,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_IF, T_ELSEIF, T_WHILE, T_FOR];
    }

    /**
     * How many times the line-start index was built and how many times the key
     * guard answered from the index already built, cumulative for the life of
     * this instance.
     *
     * @return array<string, int>
     */
    public function cacheCounts(): array
    {
        return $this->cacheCounts;
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
     * Only the condition's own tokens are considered. An array literal, a
     * subscript, a brace block, or an arrow-function body is jumped whole,
     * because its interior sits a structural level below the condition: a
     * parenthesis in there groups that construct's own expression, never the
     * enclosing boolean one. Both other walks in this class already jump the
     * same regions through the same map, so a construct cannot be opaque to
     * one walk and transparent to another.
     *
     * @return array<int>
     */
    private function groupingParentheses(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $groups = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
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

    /**
     * If the token opens a nested region, returns the pointer to that region's
     * closer so the caller can jump past it in one step; returns the pointer
     * unchanged when the token opens no region at all; returns null when it
     * opens one whose closer cannot be resolved.
     *
     * Jumping rather than counting depth is what keeps the walks linear: a
     * group's walk touches only its own direct tokens, so n nested groups cost
     * n walks of their own contents instead of n overlapping rescans of the
     * whole condition.
     *
     * The null is the third answer on purpose. "This is not a region" and
     * "this is a region I cannot resolve" were once the same return value, so
     * every caller had to re-derive the difference from the map by hand — and
     * a caller that did not was left walking a region's interior as though it
     * were the condition's own tokens. One answer per case here is what makes
     * the fail-closed branch impossible for a caller to omit.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
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
        if ($closer === null || $closer <= $i) {
            return null;
        }

        return $closer;
    }

    /**
     * Checks one parenthesized grouping: when its parentheses span multiple
     * lines, the first condition inside must sit one level deeper than the
     * line the group opens on, and every subsequent condition must align with
     * it. Each offending condition line is reported and fixed independently.
     *
     * A first condition written on the group's own opening line is the one
     * shape measured differently, because it has no indentation to measure —
     * whatever whitespace precedes it sits mid-line, and the leading
     * whitespace of the line it shares belongs to the enclosing condition.
     * It is reported under the same code and given its own line by the fixer.
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

    /**
     * Reports a first condition that shares the group's opening line, and
     * moves it onto a line of its own at the group's level.
     *
     * The standard asks for the conditions of a multi-line group to sit one
     * level deeper than the line the group opens on, which a condition glued
     * to the opening parenthesis cannot do while it stays on that line. Only
     * the first condition can reach here: every later one is preceded by a
     * condition on the same line, and only the first token of a line is
     * measured at all.
     */
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
            $phpcsFile->fixer->replaceToken(($pointer - 1), $break);

            return;
        }

        $phpcsFile->fixer->addContentBefore($pointer, $break);
    }

    /**
     * The pointers to the first token on each line that holds a condition
     * directly inside the grouping — those at nesting depth zero relative to
     * the group, skipping lines that belong to a deeper nested grouping and
     * lines that only close a bracket.
     *
     * The line cursor starts at "no line yet" rather than at the group
     * opener's line. Seeding it to the opener's line made a first condition
     * written on that same line indistinguishable from a continuation of a
     * line already collected, so it was dropped entirely and the next line
     * took its place as the group's first condition — the real first
     * condition unmeasured, the second reported as though it were the first.
     * Only a condition is affected: a comment on the opener's line is skipped
     * below before the cursor is read, so the seed never reaches it.
     *
     * @return array<int, int>
     */
    private function directConditionLines(File $phpcsFile, int $groupOpen, int $groupClose): array
    {
        $tokens = $phpcsFile->getTokens();
        $lines = [];
        $previousLine = 0;
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

    /**
     * The pointer to the first token recorded on the given token's line, read
     * from an index built once per token stream.
     *
     * Both callers used to find it by stepping backwards one token at a time
     * until the line changed. That is fine once, but each group of a condition
     * is checked in its own right, so a line carrying n stacked group openers
     * paid a walk for each of them over an ever-growing prefix of that one
     * line: n walks of 1, 2, … n tokens, quadratic in the number of groups on
     * the line. The one-opener-per-line shape was already made linear by giving
     * every walk in this class a way to jump past a nested region, but that
     * change never reached here, because these two walks are along a physical
     * line rather than through a group's contents.
     *
     * The index is keyed rather than rebuilt per call because process() runs
     * once per control structure: rebuilding it for every `if` in a file would
     * move the same quadratic cost up a level rather than remove it. The key
     * comes from TokenStreams::key(), the one implementation the four sniffs
     * with a per-stream index in this package share; what it guarantees, and
     * why identifying the File object beats describing it, is documented
     * there. This site read the token count as what separates two sources
     * analysed as STDIN, which is the invariant issue #343 disproved: two
     * STDIN sources that tokenise to the same count collided, and this index
     * answered the second analysis with the first one's pointers.
     *
     * A line the index has no entry for cannot arise — every token's own line
     * is recorded — and the fallback is the answer the old walk gave when it
     * could not move: the token itself, treated as its line's first.
     */
    private function lineStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $key = TokenStreams::key($phpcsFile);

        if ($this->lineStartsKey !== $key) {
            $this->cacheCounts['lineStarts.builds']++;
            $this->lineStartsKey = $key;
            $this->lineStarts = [];

            foreach ($tokens as $pointer => $token) {
                // Token lines never decrease, so the first pointer seen for a
                // line is the same one the backward walk used to land on.
                $this->lineStarts[$token['line']] ??= $pointer;
            }
        } else {
            $this->cacheCounts['lineStarts.hits']++;
        }

        return ($this->lineStarts[$tokens[$stackPtr]['line']] ?? $stackPtr);
    }

    /**
     * The indentation (leading-space count) of the line the given token sits on.
     */
    private function indentOfLine(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$stackPtr]['line'];

        for (
            $i = $this->lineStart($phpcsFile, $stackPtr);
            isset($tokens[$i]) === true && $tokens[$i]['line'] === $line;
            $i++
        ) {
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
        $first = $this->lineStart($phpcsFile, $pointer);
        $padding = str_repeat(' ', $expected);

        if ($tokens[$first]['code'] !== T_WHITESPACE) {
            $phpcsFile->fixer->addContentBefore($first, $padding);

            return;
        }

        $eol = preg_replace('/[^\r\n]+$/', '', $tokens[$first]['content']);
        $phpcsFile->fixer->replaceToken($first, $eol . $padding);
    }
}
