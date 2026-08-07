<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the operator line-break clean-code standard: when an expression
 * wraps across lines, the operator starts the continuation line rather than
 * dangling at the end of the previous one.
 *
 * ```php
 * // compliant — the operator leads the continuation line
 * $message = $greeting
 *     . $name;
 *
 * // flagged — the operator dangles at the end of the line
 * $message = $greeting .
 *     $name;
 * ```
 *
 * A binary assignment, comparison, logical, or concatenation operator that is
 * the last code token on its line is reported (arithmetic operators such as
 * `+`/`-` are out of scope). This mirrors the standard's two rules —
 * "operators to the right of the assignment operator should start a new line"
 * and "don't line break after comparison or assignment operators".
 *
 * An operator inside an if/elseif/while/for condition is deferred to
 * CleanCode.Conditionals.OneConditionPerLine *only where that sniff actually
 * back-stops it* — otherwise the same wrap would be reported twice, or worse,
 * slip through both. OneConditionPerLine covers exactly two cases:
 *   - a top-level boolean operator, whose placement it polices directly; and
 *   - any operator inside a *single* condition (no top-level boolean), which it
 *     collapses onto one line wholesale.
 * A dangling non-boolean operator inside a *multi*-condition (one already
 * carrying a top-level boolean) is covered by neither, so this sniff must still
 * report it. See isDeferredToOneConditionPerLine().
 *
 * Where the operator belongs on the rewritten line is a layout judgement (the
 * continuation may re-indent, merge, or split differently), so violations are
 * reported rather than auto-fixed.
 */
class OperatorLineBreakSniff implements Sniff
{
    /**
     * Bracket and brace openers whose contents are sub-expressions, skipped
     * when scanning a condition for top-level boolean operators.
     *
     * @var array<int|string>
     */
    private const BRACKET_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_merge(
            array_values(Tokens::$assignmentTokens),
            array_values(Tokens::$comparisonTokens),
            array_values(Tokens::$booleanOperators),
            [T_STRING_CONCAT]
        );
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isDeferredToOneConditionPerLine($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // Skip comments too when locating the next code token: a comment
        // explaining why an expression wraps (`$a . // note` then the operand
        // on the next line) must not mask the dangling operator.
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($next === false) {
            return;
        }

        if ($tokens[$next]['line'] <= $tokens[$stackPtr]['line']) {
            return;
        }

        $phpcsFile->addError(
            'A "%s" operator must not end a line; place it at the start of the continuation line instead',
            $stackPtr,
            'OperatorAtLineEnd',
            [$tokens[$stackPtr]['content']]
        );
    }

    /**
     * Whether a dangling operator should be left to
     * CleanCode.Conditionals.OneConditionPerLine rather than reported here.
     *
     * The operator must sit directly inside the parentheses of an
     * if/elseif/while/for — its innermost enclosing parenthesis owned by one of
     * those keywords. A boolean nested in an inner grouping paren (`if (($a &&`
     * …) has no control-structure owner, so it is still this sniff's to report.
     *
     * Within a control-structure condition, OneConditionPerLine only back-stops:
     *   - top-level boolean operators (BooleanOperatorNotLeading), and
     *   - any operator inside a single condition — one with no top-level boolean
     *     — which it collapses onto one line (SingleConditionNotOnOneLine).
     * So a boolean operator is always deferred, but a non-boolean operator is
     * deferred only when the condition has no top-level boolean; inside a
     * multi-condition it would otherwise go unreported, so this sniff owns it.
     *
     * (A for-loop's init/increment sections share the condition's parentheses;
     * the top-level-boolean scan spans all three, so a — vanishingly rare —
     * boolean in an init/increment can still tip a wrapped condition into the
     * "multi" branch. Deemed acceptable given how contrived that construct is.)
     */
    private function isDeferredToOneConditionPerLine(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $innermostOpener = max(array_keys($tokens[$stackPtr]['nested_parenthesis']));

        if (isset($tokens[$innermostOpener]['parenthesis_owner']) === false) {
            return false;
        }

        $ownerCode = $tokens[$tokens[$innermostOpener]['parenthesis_owner']]['code'];

        if (in_array($ownerCode, [T_IF, T_ELSEIF, T_WHILE, T_FOR], true) === false) {
            return false;
        }

        if (isset(Tokens::$booleanOperators[$tokens[$stackPtr]['code']]) === true) {
            return true;
        }

        return $this->conditionHasNoTopLevelBoolean($phpcsFile, $innermostOpener);
    }

    /**
     * Whether the condition delimited by $opener .. its closer carries no
     * top-level boolean operator — i.e. OneConditionPerLine treats it as a
     * single condition and collapses any wrap wholesale. Tokens nested inside a
     * further parenthesis, square bracket, or brace are sub-expressions, not the
     * condition's top level, and are skipped (mirroring OneConditionPerLine).
     */
    private function conditionHasNoTopLevelBoolean(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['parenthesis_closer'];

        for ($i = $opener + 1; $i < $closer; $i++) {
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

            if (isset(Tokens::$booleanOperators[$tokens[$i]['code']]) === true) {
                return false;
            }
        }

        return true;
    }
}
