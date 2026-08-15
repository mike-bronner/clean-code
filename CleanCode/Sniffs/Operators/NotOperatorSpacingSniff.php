<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the not-operator spacing clean-code standard: the logical-not
 * operator (`!`) is followed by exactly one space, and — because it opens a
 * (sub-)expression — carries no space between it and a preceding opening
 * square bracket.
 *
 * `if (! $test)` is compliant; `if (!$test)` (no space after) is flagged
 * (`NoSpaceAfter`); `[ ! $test]` (space between the `[` and the `!`) is flagged
 * (`SpaceBefore`).
 *
 * The space *before* the operator is only policed for array brackets. Padding
 * after an opening *parenthesis* — `if ( ! $test)`, `foo( ! $test)` — is owned
 * by PSR-12 (ControlStructureSpacing / FunctionCallSignature, already in the
 * master ruleset), which flags exactly those cases; policing them here too
 * would report the same space twice. A `!` that follows a binary operator
 * (e.g. `$a = ! $b`, `return ! $c`, `$a && ! $b`) keeps the space that
 * legitimately belongs to that preceding operator, so it is left alone.
 *
 * All violations are auto-fixable: a missing space after `!` is added, extra
 * space after `!` is collapsed to one, and the stray space before an opening-
 * bracket `!` is removed.
 */
class NotOperatorSpacingSniff implements Sniff
{
    /**
     * Opening delimiters after which a `!` must sit flush (no intervening
     * space), because the `!` opens the enclosed expression. Parentheses are
     * deliberately excluded — PSR-12 already owns `( ! ` padding; see the class
     * docblock.
     *
     * @var array<int|string, true>
     */
    private const OPENING_DELIMITERS = [
        T_OPEN_SQUARE_BRACKET => true,
        T_OPEN_SHORT_ARRAY => true,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_BOOLEAN_NOT];
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $this->checkSpaceBefore($phpcsFile, $stackPtr);
        $this->checkSpaceAfter($phpcsFile, $stackPtr);
    }

    /**
     * Flags a space between an opening array bracket `[` and the `!` it
     * precedes. Parenthesis padding is PSR-12's; see the class docblock.
     */
    private function checkSpaceBefore(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $tokens[$stackPtr - 1] ?? null;

        if ($previous === null || $previous['code'] !== T_WHITESPACE) {
            return;
        }

        // A space that spans a line break is a wrapping concern, not this
        // rule's; only the same-line "( ! " case is policed here.
        if (strpos($previous['content'], "\n") !== false) {
            return;
        }

        $delimiter = $tokens[$stackPtr - 2] ?? null;

        if ($delimiter === null || isset(self::OPENING_DELIMITERS[$delimiter['code']]) === false) {
            // The block matched here starts at this `return` and runs into the
            // head of the addFixableError() call below — the guard that owns
            // the `return` is itself outside the window, and so is the call's
            // last argument and its fixer branch. What is left is the reporting
            // signature PHP_CodeSniffer itself defines, written one argument
            // per line: message, $stackPtr, code. Its twin is TooMuchSpaceAfter's
            // report, whose guard tests an exact single space rather than a
            // delimiter token, and whose fix collapses the space *after* the
            // operator instead of deleting the one before it. The two carry no
            // shared knowledge — only a shared API — and the third report in
            // this file fixes with addContent(), so there is not even one
            // fixer verb to extract across the set.
            // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'The not operator must sit flush against the preceding "%s"; found %s space(s)',
            $stackPtr,
            'SpaceBefore',
            [$delimiter['content'], strlen($previous['content'])]
        );

        if ($fix === true) {
            $phpcsFile->fixer->replaceToken($stackPtr - 1, '');
        }
    }

    /**
     * Requires exactly one space between the `!` and its operand.
     */
    private function checkSpaceAfter(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $tokens[$stackPtr + 1] ?? null;

        if ($next === null) {
            return;
        }

        if ($next['code'] !== T_WHITESPACE) {
            $fix = $phpcsFile->addFixableError(
                'Expected 1 space after the not operator; 0 found',
                $stackPtr,
                'NoSpaceAfter'
            );

            if ($fix === true) {
                $phpcsFile->fixer->addContent($stackPtr, ' ');
            }

            return;
        }

        if ($next['content'] === ' ') {
            // The other end of the SpaceBefore match; every participating block
            // is reported at its own first line. This `return` is the
            // already-compliant exit — one space is exactly what the rule
            // wants — where the matching one above exits because the preceding
            // token is not a bracket the rule polices at all. One says "correct
            // already", the other says "not my case": opposite meanings behind
            // the same token shape, and neither report that follows can be
            // reached from the other's branch.
            // phpcs:ignore CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Expected 1 space after the not operator; %s found',
            $stackPtr,
            'TooMuchSpaceAfter',
            [strlen($next['content'])]
        );

        if ($fix === true) {
            $phpcsFile->fixer->replaceToken($stackPtr + 1, ' ');
        }
    }
}
