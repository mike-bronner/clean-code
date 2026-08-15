<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Which sniff owns a wrapped operator that sits inside an `if`/`elseif`/
 * `while`/`for` condition.
 *
 * Two sniffs police the same "an operator must lead the continuation line, not
 * trail the previous one" rule over disjoint halves of the operator list —
 * CleanCode.Operators.OperatorLineBreak over the assignment, comparison,
 * logical and concatenation operators (#35), and
 * CleanCode.Operators.ManipulationOperatorPlacement over the math and bitwise
 * ones (#59). Inside a control-structure condition a third sniff,
 * CleanCode.Conditionals.OneConditionPerLine, already reports (and auto-fixes)
 * some of the same wraps, so both have to stand down on exactly the same terms
 * or the wrap is reported twice.
 *
 * The answer is the same for both, so it lives here rather than in either of
 * them — one copy, so the two rules cannot drift apart on a construct only one
 * of them has a fixture for.
 *
 * OneConditionPerLine back-stops exactly two cases:
 *
 * - a top-level boolean operator, whose placement it polices directly
 *   (`BooleanOperatorNotLeading`); and
 * - any operator inside a *single* condition — one carrying no top-level
 *   boolean — which it collapses onto one line wholesale
 *   (`SingleConditionNotOnOneLine`).
 *
 * So a boolean operator is always deferred, and a non-boolean operator is
 * deferred only when the condition carries no top-level boolean. Inside a
 * *multi*-condition a non-boolean operator is covered by neither, and the
 * calling sniff must still report it.
 */
final class ConditionOperatorOwnership
{
    /**
     * Bracket and brace openers whose contents are sub-expressions, skipped
     * when scanning a condition for top-level boolean operators.
     *
     * @var array<int|string>
     */
    private const BRACKET_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET];

    /**
     * The control structures whose condition OneConditionPerLine polices.
     *
     * @var array<int|string>
     */
    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR];

    /**
     * Whether the operator at $stackPtr should be left to
     * CleanCode.Conditionals.OneConditionPerLine rather than reported by the
     * calling sniff.
     *
     * The operator must sit directly inside the parentheses of an
     * if/elseif/while/for — its innermost enclosing parenthesis owned by one of
     * those keywords. A boolean nested in an inner grouping paren (`if (($a &&`
     * …) has no control-structure owner, so it stays with the caller.
     *
     * (A for-loop's init/increment sections share the condition's parentheses;
     * the top-level-boolean scan spans all three, so a — vanishingly rare —
     * boolean in an init/increment can still tip a wrapped condition into the
     * "multi" branch. Deemed acceptable given how contrived that construct is.)
     */
    public static function isDeferredToOneConditionPerLine(File $phpcsFile, int $stackPtr): bool
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

        if (in_array($ownerCode, self::CONDITION_OWNERS, true) === false) {
            return false;
        }

        if (isset(Tokens::$booleanOperators[$tokens[$stackPtr]['code']]) === true) {
            return true;
        }

        return self::conditionHasNoTopLevelBoolean($phpcsFile, $innermostOpener);
    }

    /**
     * Whether the condition delimited by $opener .. its closer carries no
     * top-level boolean operator — i.e. OneConditionPerLine treats it as a
     * single condition and collapses any wrap wholesale. Tokens nested inside a
     * further parenthesis, square bracket, or brace are sub-expressions, not the
     * condition's top level, and are skipped (mirroring OneConditionPerLine).
     */
    private static function conditionHasNoTopLevelBoolean(File $phpcsFile, int $opener): bool
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
