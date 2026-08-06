<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Closes the one detection gap between PHPMD's CleanCode/IfStatementAssignment
 * rule (#79) and Generic.CodeAnalysis.AssignmentInCondition, which rules.xml
 * wires in to replace it.
 *
 * The Generic sniff decides an assignment is worth reporting by walking back
 * from the "=" to the start of the condition and requiring a T_VARIABLE or a
 * T_CLOSE_SQUARE_BRACKET on the left. A list() destructuring target ends in a
 * T_CLOSE_PARENTHESIS instead, which that walk treats as "a function call, so
 * we are OK" and abandons — so `if (list($a, $b) = $data)` is silent there
 * while PHPMD flags it. Short-list destructuring (`if ([$a, $b] = $data)`)
 * ends in "]" and is already covered, so only the long form needs this sniff.
 *
 * The condition set mirrors the Generic sniff's rather than PHPMD's narrower
 * if/elseif pair, so the two sniffs together report one consistent rule across
 * every condition. T_CASE is the one construct left out: it has no
 * parentheses to anchor the enclosure test, and a list() assignment is not
 * expressible in a case label.
 *
 * Report-only. PHPMD offers no fixer for this rule, and there is no mechanical
 * rewrite of a destructuring-assignment condition into a comparison.
 */
class DisallowListAssignmentInConditionSniff implements Sniff
{
    /**
     * Control structures whose parentheses hold a condition. Mirrors the
     * Generic sniff's register() minus T_CASE (see the class docblock).
     *
     * @var array<int|string>
     */
    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_FOR, T_SWITCH, T_WHILE, T_MATCH];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_LIST];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated list() carries a null closer, so there is no
        // position to look past for an "=". Refuse rather than search from a
        // made-up offset. Dropping this guard does not change what any
        // fixture here reports — a search from the bogus offset lands on a
        // token that is not "=", and the check below rejects it — so the
        // guard is explicitness, not a behaviour the tests can pin.
        $closer = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if ($closer === null) {
            return;
        }

        $assignment = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        if ($assignment === false || $tokens[$assignment]['code'] !== T_EQUAL) {
            return;
        }

        if ($this->isInsideCondition($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Variable assignment found within a condition. Did you mean to do a comparison ?',
            $assignment,
            'Found'
        );
    }

    /**
     * Whether the token sits inside the condition parentheses of one of the
     * control structures above. Every enclosing parenthesis is considered, so
     * a list() nested in a call argument or a closure body inside the
     * condition counts — matching what PHPMD reports, since its own search
     * descends the whole condition expression.
     */
    private function isInsideCondition(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['nested_parenthesis']) === false) {
            return false;
        }

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis']) as $opener) {
            if (isset($tokens[$opener]['parenthesis_owner']) === false) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'];

            if (in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true) === false) {
                continue;
            }

            if (
                $tokens[$owner]['code'] === T_FOR
                && $this->isInForConditionSection($phpcsFile, $owner, $stackPtr) === false
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Whether the token sits in a for-loop's middle section — the only one of
     * the three that is a condition. Assignments in the initialiser and the
     * increment are ordinary, so the Generic sniff bounds its search the same
     * way.
     *
     * The middle section is the one with a semicolon on both sides inside the
     * header: the initialiser has none before it, the increment none after.
     * A malformed header with no semicolon at all therefore falls out as "not
     * a condition" through the same test, with nothing to guess at.
     */
    private function isInForConditionSection(File $phpcsFile, int $forPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $before = $phpcsFile->findPrevious(
            T_SEMICOLON,
            ($stackPtr - 1),
            $tokens[$forPtr]['parenthesis_opener']
        );
        $after = $phpcsFile->findNext(
            T_SEMICOLON,
            ($stackPtr + 1),
            $tokens[$forPtr]['parenthesis_closer']
        );

        return $before !== false && $after !== false;
    }
}
