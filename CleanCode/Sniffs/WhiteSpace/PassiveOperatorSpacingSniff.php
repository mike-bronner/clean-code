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
     * Tokens that, immediately before a `+`/`-`, mark it as a binary operator
     * (an operand it is acting on precedes it). Anything else preceding a
     * `+`/`-` — an operator, keyword, open bracket, comma, or the start of a
     * statement — makes it a unary sign.
     *
     * @var array<int, int>
     */
    private const OPERAND_END_TOKENS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_STRING,
        T_CONSTANT_ENCAPSED_STRING,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_SHORT_ARRAY,
    ];

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
            $this->reportSpaceAfter($phpcsFile, $stackPtr, 'ErrorControl', '@', false);

            return;
        }

        // T_PLUS / T_MINUS are only passive operators when used as a unary sign.
        if ($this->isUnarySign($phpcsFile, $stackPtr) === false) {
            return;
        }

        [$errorCode, $symbol] = $code === T_PLUS ? ['Identity', '+'] : ['Negation', '-'];

        $this->reportSpaceAfter($phpcsFile, $stackPtr, $errorCode, $symbol, true);
    }

    /**
     * A `+`/`-` is a unary sign unless the previous non-empty token produces a
     * value for it to operate on (a binary operator).
     */
    private function isUnarySign(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return true;
        }

        return in_array($phpcsFile->getTokens()[$previous]['code'], self::OPERAND_END_TOKENS, true) === false;
    }

    /**
     * Flags — and removes — same-line whitespace between a prefix operator and
     * its operand. When $guardSignMerge is set, a fix is withheld if the
     * operand begins with another sign (`- -$a`, `+ +$a`), since closing the
     * gap would fuse the pair into a decrement/increment and change meaning.
     */
    private function reportSpaceAfter(
        File $phpcsFile,
        int $stackPtr,
        string $errorCode,
        string $symbol,
        bool $guardSignMerge
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
            $guardSignMerge === true
            && in_array($tokens[$operand]['code'], [T_PLUS, T_MINUS, T_INC, T_DEC], true) === true
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
