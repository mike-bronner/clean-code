<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class PassiveOperatorSpacingSniff implements Sniff
{
    private ?array $nonOperandTokens = null;

    public function register(): array
    {
        return [T_PLUS, T_MINUS, T_ASPERAND, T_BACKTICK];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_BACKTICK) {
            if ($this->isBacktickOpener($phpcsFile, $stackPtr)) {
                $this->processBacktickString($phpcsFile, $stackPtr);
            }

            return;
        }

        if ($code === T_ASPERAND) {
            // No guard: `@` fuses with nothing. A sign operand (`@ -$a` →
            // `@-$a`) used to oscillate against the ruleset's binary-operator
            // spacing sniff, which re-spaced the `-`; that sniff is now
            // CleanCode.Operators.BinaryOperatorSpacing, which cedes a sign
            // directly after `@`, so the fix is stable.
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

    private function isUnarySign(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return true;
        }

        return isset($this->nonOperandTokens()[$phpcsFile->getTokens()[$previous]['code']]) === true;
    }

    private function nonOperandTokens(): array
    {
        if ($this->nonOperandTokens === null) {
            $this->nonOperandTokens = Tokens::$operators
                + Tokens::$comparisonTokens
                + Tokens::$booleanOperators
                + Tokens::$assignmentTokens
                + Tokens::$castTokens
                + [
                    T_ASPERAND => T_ASPERAND,
                    T_OPEN_TAG => T_OPEN_TAG,
                    T_OPEN_TAG_WITH_ECHO => T_OPEN_TAG_WITH_ECHO,
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
                ];
        }

        return $this->nonOperandTokens;
    }

    private function reportSpaceAfter(
        File $phpcsFile,
        int $stackPtr,
        string $errorCode,
        string $symbol,
        array $guardTokens
    ): void {
        $tokens = $phpcsFile->getTokens();
        $next = ($stackPtr + 1);

        if (
            isset($tokens[$next]) === false
            || $tokens[$next]['code'] !== T_WHITESPACE
        ) {
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
            "No space allowed between the passive \"%s\" operator and its operand",
            $stackPtr,
            $errorCode,
            [$symbol]
        );

        if ($fix === true) {
            $phpcsFile->fixer
                ->replaceToken($next, '');
        }
    }

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

    private function trimBacktickContent(File $phpcsFile, int $reportPtr, int $contentPtr, string $pattern): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$contentPtr]['code'] !== T_ENCAPSED_AND_WHITESPACE) {
            return;
        }

        $content = $tokens[$contentPtr]['content'];

        // A failed read is null, and `null === $content` is false, so the
        // failure would fall past the guard below and hand null to the fixer as
        // the replacement text. The content itself is the honest fallback: it
        // reads as "nothing to trim", which leaves the file as written and the
        // violation unreported rather than emptying the backticks. The three
        // patterns this is called with — `/^[ \t]+|[ \t]+$/`, `/^[ \t]+/` and
        // `/[ \t]+$/` — each quantify one character class against an anchor,
        // and none carries a `/u` modifier.
        $trimmed = preg_replace($pattern, '', $content) ?? $content;

        if ($trimmed === $content) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Execution backticks must sit flush against the command; remove the surrounding space',
            $reportPtr,
            'Execution'
        );

        if ($fix === true) {
            $phpcsFile->fixer
                ->replaceToken($contentPtr, $trimmed);
        }
    }
}
