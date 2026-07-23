<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Operators: Passive" standard — a passive operator must sit
 * flush against the operand it acts on, with no intervening space.
 *
 * This is the anchor sniff for the standard, covering the passive operators
 * that no existing PHPCS/Slevomat sniff enforces:
 *
 * - **Identity** — unary `+` (`+$a`, not `+ $a`).
 * - **Negation** — unary `-` (`-$a`, not `- $a`).
 * - **Error control** — `@` (`@file_get_contents(...)`, not `@ file_get_contents(...)`).
 * - **Execution** — backticks (`` `ls` ``, not `` ` ls ` ``).
 *
 * The remaining passive operators are enforced by existing sniffs wired into
 * the master `rules.xml` alongside this one: increment/decrement by
 * `Generic.WhiteSpace.IncrementDecrementSpacing`, the object operator `->` by
 * `Squiz.WhiteSpace.ObjectOperatorSpacing`, and array access `[]` by
 * `Squiz.Arrays.ArrayBracketSpacing`.
 *
 * Binary `+`/`-` (`$a + $b`) is a different operator — its spacing is out of
 * scope and left untouched.
 */
class PassiveOperatorSpacingSniff implements Sniff
{
    /**
     * Tokens that, immediately before a `+`/`-`, mark it as a *unary* sign —
     * there is no value to its left for it to operate on binary-style. This is
     * an exclusion set (operators, comparisons, boolean/assignment operators,
     * casts, statement-introducing keywords, open brackets, commas, and
     * statement boundaries): when the previous token is one of these the sign
     * is unary; otherwise a value precedes it and the `+`/`-` is binary.
     *
     * Keying off "not an operand" rather than enumerating every operand-ending
     * token is what the reference Squiz.WhiteSpace.OperatorSpacing sniff does,
     * and it fails safe — an unlisted value-producing token (`$a++`, a heredoc
     * close, …) defaults to binary and is left untouched, never mis-fixed.
     *
     * Built lazily because it draws on PHPCS's runtime Tokens arrays.
     *
     * @var array<int|string, int|string>|null
     */
    private ?array $nonOperandTokens = null;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_PLUS, T_MINUS, T_ASPERAND, T_BACKTICK];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_BACKTICK) {
            if ($this->isBacktickOpener($phpcsFile, $stackPtr)) {
                $this->processBacktickString($phpcsFile, $stackPtr);
            }

            return;
        }

        if ($code === T_ASPERAND) {
            $this->reportSpaceAfter($phpcsFile, $stackPtr, 'ErrorControl', '@', []);

            return;
        }

        // T_PLUS / T_MINUS are only passive operators when used as a unary sign.
        if ($this->isUnarySign($phpcsFile, $stackPtr) === false) {
            return;
        }

        // The guard suppresses a fix only when closing the gap would fuse the
        // sign into a same-direction increment/decrement (`- -$a` → `--$a`,
        // `+ +$a` → `++$a`) and change meaning. A cross-direction pair
        // (`- ++$a` → `-++$a`, `+ --$a` → `+--$a`) does not fuse and is fixed.
        [$errorCode, $symbol, $guardTokens] = $code === T_PLUS
            ? ['Identity', '+', [T_PLUS, T_INC]]
            : ['Negation', '-', [T_MINUS, T_DEC]];

        $this->reportSpaceAfter($phpcsFile, $stackPtr, $errorCode, $symbol, $guardTokens);
    }

    /**
     * A `+`/`-` is a unary sign unless a value precedes it (making it binary).
     * The sign is unary at the start of a statement, or when the previous
     * non-empty token is a non-operand token (an operator, keyword, open
     * bracket, comma, …); anything else — a variable, literal, closing
     * bracket, `$a++`, a heredoc close — is a value and makes the sign binary.
     */
    private function isUnarySign(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return true;
        }

        return isset($this->nonOperandTokens()[$phpcsFile->getTokens()[$previous]['code']]) === true;
    }

    /**
     * The set of tokens that, immediately before a `+`/`-`, mark it as unary.
     * Mirrors the reference Squiz.WhiteSpace.OperatorSpacing sniff's operand
     * detection so the two agree on what counts as a binary operand.
     *
     * @return array<int|string, int|string>
     */
    private function nonOperandTokens(): array
    {
        if ($this->nonOperandTokens === null) {
            $this->nonOperandTokens = Tokens::$operators
                + Tokens::$comparisonTokens
                + Tokens::$booleanOperators
                + Tokens::$assignmentTokens
                + Tokens::$castTokens
                + [
                    T_RETURN => T_RETURN,
                    T_ECHO => T_ECHO,
                    T_PRINT => T_PRINT,
                    T_EXIT => T_EXIT,
                    T_YIELD => T_YIELD,
                    T_FN_ARROW => T_FN_ARROW,
                    T_MATCH_ARROW => T_MATCH_ARROW,
                    T_CASE => T_CASE,
                    T_COLON => T_COLON,
                    T_COMMA => T_COMMA,
                    T_INLINE_ELSE => T_INLINE_ELSE,
                    T_INLINE_THEN => T_INLINE_THEN,
                    T_STRING_CONCAT => T_STRING_CONCAT,
                    T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET,
                    T_OPEN_PARENTHESIS => T_OPEN_PARENTHESIS,
                    T_OPEN_SHORT_ARRAY => T_OPEN_SHORT_ARRAY,
                    T_OPEN_SQUARE_BRACKET => T_OPEN_SQUARE_BRACKET,
                    T_SEMICOLON => T_SEMICOLON,
                    T_OPEN_TAG => T_OPEN_TAG,
                    T_OPEN_TAG_WITH_ECHO => T_OPEN_TAG_WITH_ECHO,
                ];
        }

        return $this->nonOperandTokens;
    }

    /**
     * Flags — and removes — same-line whitespace between a prefix operator and
     * its operand. $guardTokens lists the operand-leading tokens for which a
     * fix (and report) is withheld because closing the gap would fuse the pair
     * into a same-direction decrement/increment and change meaning: `[T_MINUS,
     * T_DEC]` for `-` (`- -$a` → `--$a`), `[T_PLUS, T_INC]` for `+`. Empty for
     * operators that never fuse (`@`).
     *
     * @param array<int, int> $guardTokens
     */
    private function reportSpaceAfter(
        File $phpcsFile,
        int $stackPtr,
        string $errorCode,
        string $symbol,
        array $guardTokens
    ): void {
        $tokens = $phpcsFile->getTokens();
        $next = ($stackPtr + 1);

        if (isset($tokens[$next]) === false || $tokens[$next]['code'] !== T_WHITESPACE) {
            return;
        }

        // Whitespace spanning a line break is a wrapping concern, out of scope.
        if (strpos($tokens[$next]['content'], "\n") !== false) {
            return;
        }

        $operand = $phpcsFile->findNext(T_WHITESPACE, $next, null, true);

        if ($operand === false) {
            return;
        }

        if (
            $guardTokens !== []
            && in_array($tokens[$operand]['code'], $guardTokens, true) === true
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'No space allowed between the passive "%s" operator and its operand',
            $stackPtr,
            $errorCode,
            [$symbol]
        );

        if ($fix === true) {
            $phpcsFile->fixer->replaceToken($next, '');
        }
    }

    /**
     * A backtick opens an execution string when an even number of backticks
     * precede it (0, 2, 4, …); odd-position backticks are the closers, already
     * handled from their opener.
     */
    private function isBacktickOpener(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $preceding = 0;

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if ($tokens[$i]['code'] === T_BACKTICK) {
                $preceding++;
            }
        }

        return ($preceding % 2) === 0;
    }

    /**
     * Flags — and trims — horizontal whitespace directly inside the backticks,
     * from the opener to its matching closer. Line breaks inside the command
     * are left alone.
     */
    private function processBacktickString(File $phpcsFile, int $openPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $closePtr = $phpcsFile->findNext(T_BACKTICK, ($openPtr + 1));

        if ($closePtr === false) {
            return;
        }

        $first = ($openPtr + 1);
        $last = ($closePtr - 1);

        if ($first > $last) {
            return;
        }

        // A single content token is bounded by both backticks, so both edges
        // must be trimmed in one replacement to avoid conflicting fixes.
        if ($first === $last) {
            $this->trimBacktickContent($phpcsFile, $openPtr, $first, '/^[ \t]+|[ \t]+$/');

            return;
        }

        $this->trimBacktickContent($phpcsFile, $openPtr, $first, '/^[ \t]+/');
        $this->trimBacktickContent($phpcsFile, $closePtr, $last, '/[ \t]+$/');
    }

    /**
     * Reports and strips the whitespace matched by $pattern from the content
     * token at $contentPtr, attributing the violation to $reportPtr.
     */
    private function trimBacktickContent(File $phpcsFile, int $reportPtr, int $contentPtr, string $pattern): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$contentPtr]['code'] !== T_ENCAPSED_AND_WHITESPACE) {
            return;
        }

        $content = $tokens[$contentPtr]['content'];
        $trimmed = preg_replace($pattern, '', $content);

        if ($trimmed === $content) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Execution backticks must sit flush against the command; remove the surrounding space',
            $reportPtr,
            'Execution'
        );

        if ($fix === true) {
            $phpcsFile->fixer->replaceToken($contentPtr, $trimmed);
        }
    }
}
