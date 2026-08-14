<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's CleanCode/ElseExpression: an `if` statement's `else`
 * branch is always avoidable with an early exit or a guard clause, so every
 * one is reported.
 *
 * Two deliberate boundaries, both matching PHPMD (measured against PHPMD
 * 2.15.0 — docs/phpmd/cleancode-elseexpression.md carries the full mapping):
 *
 *  - `elseif` is not reported. PHPMD's rule only ever fires on the *else*
 *    scope — the third child of an `if`/`elseif` node — so an `if`/`elseif`
 *    chain with no closing `else` produces nothing. T_ELSEIF is therefore not
 *    registered at all.
 *  - The two-word `else if` form is not reported either, for the same reason:
 *    PHP parses it as an `elseif`, and PHPMD stays silent on it. Reporting it
 *    would discriminate on spelling alone. A closing `else` *after* such a
 *    chain is still reported — that one is a real else scope.
 *
 * PHP allows the reserved word `else` as a member name — a method or class
 * constant since 7.0, an enum case since 8.1 — and `token_get_all()` still
 * returns T_ELSE for the declaration and for a `Foo::else` reference. No guard
 * is needed here: PHPCS's own tokenizer rewrites every one of those to
 * T_STRING before a sniff sees it, so this sniff never registers on one.
 * tests/fixtures/DisallowElseSniff/passing.php carries each shape, which keeps
 * that dependency honest if the tokenizer ever changes.
 */
class DisallowElseSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_ELSE];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // The `!== false` honours findNext()'s int|false contract. It is not a
        // behavioural branch and carries no fixture: reaching it would need a
        // file whose very last token is `else`, which is not parseable PHP.
        if ($next !== false && $tokens[$next]['code'] === T_IF) {
            return;
        }

        $phpcsFile->addError(
            'Else clauses are unnecessary; use an early exit or a guard clause instead',
            $stackPtr,
            'Found'
        );
    }
}
