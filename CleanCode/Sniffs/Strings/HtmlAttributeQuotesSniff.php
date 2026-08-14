<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\Markup;
use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the Strings standard's "HTML attributes should always use quotes,
 * never apostrophes" rule (#25).
 *
 * When a string literal contains an HTML tag, this sniff flags attribute values
 * written with apostrophes (`class='card'`) and rewrites them to double quotes.
 * The rewrite is scoped to actual tag spans (`<… >`), so an apostrophe in prose
 * or SQL between or outside tags — `<p>WHERE name = 'x'</p>` — is never touched;
 * only apostrophes that delimit an attribute inside a tag are converted.
 *
 * Both PHP string contexts are handled. In a double-quoted PHP string the
 * apostrophes are literal (`"<a class='card'>"`) and the replacement quotes are
 * escaped for that context (`"<a class=\"card\">"`). In a single-quoted PHP
 * string the attribute apostrophes are themselves escaped (`'<a class=\'card\'>'`)
 * and the replacement needs no escaping (`'<a class="card">'`). A multi-line
 * double-quoted string is tokenized one token per physical line; continuation
 * lines (whose token does not begin with a quote) are scanned too, so an
 * apostrophe attribute on any line is caught — the one boundary left alone is a
 * single tag physically split across lines, whose span no single token holds.
 *
 * An attribute whose value already contains a double quote is reported but left
 * for manual conversion (its escaping is ambiguous).
 */
class HtmlAttributeQuotesSniff implements Sniff
{
    /**
     * String token codes this sniff registers. A multi-line double-quoted
     * string splits into several of these — one per physical line.
     */
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::STRING_TOKENS;
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

        // The escaped-apostrophe sequence as it appears in this PHP string
        // context: a literal `'` inside a double-quoted string, `\'` inside a
        // single-quoted one.
        $apostrophe = $this->phpStringDelimiter($phpcsFile, $stackPtr) === "'" ? "\\'" : "'";

        if ($this->hasApostropheAttribute($content, $apostrophe) === false) {
            return;
        }

        $fixed = $this->rewriteAttributes($content, $apostrophe);

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
     * Whether any tag span in $content holds an apostrophe-delimited attribute.
     */
    private function hasApostropheAttribute(string $content, string $apostrophe): bool
    {
        foreach ($this->tagSpans($content) as $span) {
            if (preg_match($this->attributePattern($apostrophe), $span) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rewrites every apostrophe-quoted attribute inside a tag span to double
     * quotes, escaped as the PHP string context requires, or returns null when
     * any such value contains a double quote (its escaping is ambiguous and
     * left to a human). Text outside tag spans is preserved verbatim.
     */
    private function rewriteAttributes(string $content, string $apostrophe): ?string
    {
        // Double-quoted PHP context escapes the replacement quotes (`\"`);
        // single-quoted context takes them literally (`"`).
        $quote = $apostrophe === "\\'" ? '"' : '\\"';
        $ambiguous = false;

        $rewritten = preg_replace_callback(
            Markup::tagSpanPattern(),
            function (array $match) use ($apostrophe, $quote, &$ambiguous): string {
                return (string) preg_replace_callback(
                    $this->attributePattern($apostrophe),
                    static function (array $attr) use ($quote, &$ambiguous): string {
                        if (strpos($attr[2], '"') !== false) {
                            $ambiguous = true;

                            return $attr[0];
                        }

                        return $attr[1] . '=' . $quote . $attr[2] . $quote;
                    },
                    $match[0]
                );
            },
            $content
        );

        return $ambiguous ? null : $rewritten;
    }

    /**
     * All opening/self-closing tag spans in $content (closing tags carry no
     * attributes and are excluded).
     *
     * @return array<int, string>
     */
    private function tagSpans(string $content): array
    {
        if (preg_match_all(Markup::tagSpanPattern(), $content, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    /**
     * Regex matching an HTML attribute whose value is delimited by the given
     * apostrophe sequence: `name=<q>value<q>`. The value may not contain a bare
     * apostrophe (that would close it), which mirrors PHP's own constraint.
     */
    private function attributePattern(string $apostrophe): string
    {
        $quoted = preg_quote($apostrophe, '#');

        return '#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\\s*=\\s*' . $quoted . '([^\']*)' . $quoted . '#';
    }

    /**
     * The PHP-source delimiter (`"` or `'`) of the string literal $stackPtr
     * belongs to. A multi-line string splits into several tokens; only the
     * first opens with a quote, so a continuation token is resolved by walking
     * back over the contiguous string tokens to that opener. An unresolved
     * continuation defaults to `"` — a single-quoted literal cannot interpolate,
     * so continuation tokens of an interpolated string are always double-quoted.
     *
     * The opener is read through StringLiteral, not off the token's first
     * character, so a binary-string prefix (`B'<a class=\'x\'>'`) does not hide
     * the delimiter and send the whole literal down the double-quoted path.
     */
    private function phpStringDelimiter(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $pointer = $stackPtr;

        while ($pointer >= 0 && in_array($tokens[$pointer]['code'], self::STRING_TOKENS, true) === true) {
            $delimiter = StringLiteral::delimiter($tokens[$pointer]['content']);

            if ($delimiter !== null) {
                return $delimiter;
            }

            $pointer--;
        }

        return '"';
    }
}
