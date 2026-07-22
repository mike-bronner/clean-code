<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\Markup;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the Strings standard's "HTML attributes should always use quotes,
 * never apostrophes" rule (#25).
 *
 * When a string literal contains HTML, this sniff flags attribute values
 * written with apostrophes (`class='card'`) and rewrites them to double
 * quotes. Because an apostrophe-quoted attribute can only survive inside a
 * *double*-quoted PHP string (a single-quoted PHP string would be terminated
 * by the apostrophe), the fixer escapes the replacement quotes for that
 * context: `"<a class='card'>"` → `"<a class=\"card\">"`.
 *
 * The apostrophe scan is gated on the string actually containing an HTML
 * element (see {@see Markup}), so SQL and prose such as
 * `"WHERE name = 'x'"` is never touched. An attribute whose value already
 * contains a double quote is reported but left for manual conversion.
 */
class HtmlAttributeQuotesSniff implements Sniff
{
    /**
     * Matches an HTML attribute whose value is delimited by apostrophes:
     * `name='value'`. The value itself may not contain an apostrophe (that
     * would close it), which is exactly PHP's own constraint.
     */
    private const APOSTROPHE_ATTRIBUTE = "#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\\s*=\\s*'([^']*)'#";

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING];
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

        if ($content[0] !== '"') {
            return;
        }

        if (Markup::containsHtmlElement($content) === false) {
            return;
        }

        if (preg_match(self::APOSTROPHE_ATTRIBUTE, $content) !== 1) {
            return;
        }

        $fixed = $this->rewriteAttributes($content);

        if ($fixed === null) {
            $phpcsFile->addError(
                'HTML attributes must use double quotes, not apostrophes; the value contains a'
                    . ' double quote, so convert this attribute manually',
                $stackPtr,
                'Apostrophe'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'HTML attributes must use double quotes, not apostrophes',
            $stackPtr,
            'Apostrophe'
        );

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer->replaceToken($stackPtr, $fixed);
    }

    /**
     * Rewrites every apostrophe-quoted attribute in a double-quoted PHP string
     * token to escaped double quotes, or returns null when a value contains a
     * double quote (its escaping is ambiguous and left to a human).
     */
    private function rewriteAttributes(string $content): ?string
    {
        if ($this->valueHasDoubleQuote($content) === true) {
            return null;
        }

        return preg_replace_callback(
            self::APOSTROPHE_ATTRIBUTE,
            static fn (array $match): string => $match[1] . '=\\"' . $match[2] . '\\"',
            $content
        );
    }

    /**
     * Whether any apostrophe-delimited attribute value carries a double-quote
     * character (as the raw `"` or the escaped `\"` sequence).
     */
    private function valueHasDoubleQuote(string $content): bool
    {
        if (preg_match_all(self::APOSTROPHE_ATTRIBUTE, $content, $matches) === false) {
            return false;
        }

        foreach ($matches[2] as $value) {
            if (strpos($value, '"') !== false) {
                return true;
            }
        }

        return false;
    }
}
