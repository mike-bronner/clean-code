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
 *
 * Both readings are confined to the span OneConditionPerLine actually walks —
 * {@see self::checkedRegion()} — which that sniff resolves from this same
 * method, so the deferral boundary cannot drift from the boundary it defers to.
 */
final class ConditionOperatorOwnership
{
    /**
     * Bracket and brace openers whose contents are sub-expressions, skipped
     * by {@see self::findTopLevelTokens()}.
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
     * Sitting inside those parentheses is not enough: a for-loop's init and
     * increment clauses share them with the condition, and OneConditionPerLine
     * confines every check it makes to the condition clause alone. An operator
     * outside {@see self::checkedRegion()} is therefore reported by the caller,
     * whatever the header holds — deferring it would drop the violation, since
     * the sniff deferred to never walks that far.
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

        $owner = $tokens[$innermostOpener]['parenthesis_owner'];

        if (in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true) === false) {
            return false;
        }

        $region = self::checkedRegion($phpcsFile, $owner);

        if ($region === null) {
            return false;
        }

        [$regionStart, $regionEnd] = $region;

        if ($stackPtr <= $regionStart || $stackPtr >= $regionEnd) {
            return false;
        }

        if (isset(Tokens::$booleanOperators[$tokens[$stackPtr]['code']]) === true) {
            return true;
        }

        // No top-level boolean in the checked region: OneConditionPerLine reads
        // it as a single condition and collapses any wrap in it wholesale.
        return self::findTopLevelTokens(
            $phpcsFile,
            ($regionStart + 1),
            ($regionEnd - 1),
            array_keys(Tokens::$booleanOperators)
        ) === [];
    }

    /**
     * The token span CleanCode.Conditionals.OneConditionPerLine checks for the
     * control structure at $stackPtr, as the exclusive bounds
     * [$regionStart, $regionEnd] — or null when it checks nothing at all.
     *
     * For an if/elseif/while that span is the condition parentheses. For a
     * for-loop it is the condition clause only: the two top-level semicolons
     * delimit it, and a header without exactly two of them is one that sniff
     * declines to process.
     *
     * OneConditionPerLine resolves its own boundaries from here, so this is the
     * single definition of "what that sniff looks at" rather than a copy of it.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function checkedRegion(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]['parenthesis_opener']) === false
            || isset($tokens[$stackPtr]['parenthesis_closer']) === false
        ) {
            return null;
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        if ($tokens[$stackPtr]['code'] !== T_FOR) {
            return [$opener, $closer];
        }

        $semicolons = self::findTopLevelTokens($phpcsFile, ($opener + 1), ($closer - 1), [T_SEMICOLON]);

        return count($semicolons) === 2 ? [$semicolons[0], $semicolons[1]] : null;
    }

    /**
     * Collects pointers to the given token codes between $start and $end
     * inclusive, skipping everything nested inside parentheses, square
     * brackets, or curly braces — those belong to sub-expressions, not to the
     * top level of the clause.
     *
     * @param array<int|string> $codes
     *
     * @return array<int>
     */
    public static function findTopLevelTokens(File $phpcsFile, int $start, int $end, array $codes): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($i = $start; $i <= $end; $i++) {
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

            if (in_array($tokens[$i]['code'], $codes, true) === true) {
                $pointers[] = $i;
            }
        }

        return $pointers;
    }
}
