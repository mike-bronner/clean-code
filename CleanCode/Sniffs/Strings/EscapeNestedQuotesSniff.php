<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the Strings standard's "escape quotes when rendering inside other
 * quotes" rule (#25).
 *
 * The standard's rationale — "strings quoted in the same way can be sorted"
 * and "reduced mental load when reading nested quotes" — makes the double
 * quote the canonical delimiter: when a string must contain a double-quote
 * character, escape it (`\"`) rather than switching the whole literal to
 * single quotes just to dodge the escape. So this sniff flags a single-quoted
 * literal that carries a double-quote character — `'He said "hi"'` — and, when
 * safe, rewrites it to `"He said \"hi\""`.
 *
 * (PHP cannot tokenize an *un*escaped delimiter nested in a same-quoted string
 * at all — `"a"b"` is a syntax error — so the reachable form of the rule is
 * exactly this delimiter-switch dodge, which the sniff normalizes.)
 *
 * The fixer runs only when the conversion cannot change meaning: the literal
 * carries no `$` or `{` (which would start interpolation under double quotes)
 * and no backslash escape (whose meaning differs between quote styles).
 * Otherwise the violation is reported for manual conversion.
 *
 * Only a whole literal is considered, and its delimiter is read past any
 * binary-string prefix — see the Support\StringLiteral docblock for why the
 * token's first character answers neither question.
 *
 * The fixer carries that prefix onto its output, which is safe only because the
 * `$`/`{` guard above has already run: PHP_CodeSniffer reads a prefixed
 * *non*-interpolating string (`B"He said \"hi\""`) as an ordinary literal, but
 * cannot tokenize a prefixed interpolating one (`B"…$value…"`) at all. The two
 * guards are therefore coupled — admitting `$` here would make this fixer emit
 * source the tokenizer cannot read. Its sibling RequireStringInterpolation
 * refuses a prefixed literal outright for exactly that reason: its own output
 * always interpolates.
 */
class EscapeNestedQuotesSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        if (StringLiteral::isComplete($content) === false || StringLiteral::delimiter($content) !== "'") {
            return;
        }

        $inner = StringLiteral::inner($content);

        if (strpos($inner, '"') === false) {
            return;
        }

        if ($this->isSafeToConvert($inner) === false) {
            $phpcsFile->addError(
                'Prefer a double-quoted string with escaped inner quotes over single quotes;'
                    . ' this literal needs manual conversion (it contains a variable, brace, or escape)',
                $stackPtr,
                'UnescapedQuote'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Use a double-quoted string with escaped inner quotes instead of switching to single'
                . ' quotes to avoid escaping',
            $stackPtr,
            'UnescapedQuote'
        );

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer->replaceToken(
            $stackPtr,
            StringLiteral::prefix($content) . '"' . str_replace('"', '\\"', $inner) . '"'
        );
    }

    /**
     * Whether a single-quoted literal's inner text can be re-delimited with
     * double quotes without changing meaning: no interpolation triggers
     * (`$`, `{`) and no backslash escapes.
     */
    private function isSafeToConvert(string $inner): bool
    {
        return strpbrk($inner, '${\\') === false;
    }
}
