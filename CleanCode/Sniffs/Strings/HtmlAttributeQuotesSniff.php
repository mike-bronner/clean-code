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
 * An attribute whose value carries a double quote or a backslash is reported but
 * left for manual conversion — the first is ambiguous to re-delimit, the second
 * can merge with the injected escape and break the PHP string. See
 * isSafeToConvert().
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
                    . ' double quote or a backslash, so convert this attribute manually',
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
     * any such value is unsafe to re-delimit (see isSafeToConvert()). Text
     * outside tag spans is preserved verbatim.
     */
    private function rewriteAttributes(string $content, string $apostrophe): ?string
    {
        // Double-quoted PHP context escapes the replacement quotes (`\"`);
        // single-quoted context takes them literally (`"`).
        $quote = $apostrophe === "\\'" ? '"' : '\\"';
        $unsafe = false;

        $rewritten = preg_replace_callback(
            Markup::tagSpanPattern(),
            function (array $match) use ($apostrophe, $quote, &$unsafe): string {
                return (string) preg_replace_callback(
                    $this->attributePattern($apostrophe),
                    static function (array $attr) use ($quote, &$unsafe): string {
                        if (self::isSafeToConvert($attr[2]) === false) {
                            $unsafe = true;

                            return $attr[0];
                        }

                        return $attr[1] . '=' . $quote . $attr[2] . $quote;
                    },
                    $match[0]
                );
            },
            $content
        );

        return $unsafe ? null : $rewritten;
    }

    /**
     * Whether an attribute value can be re-delimited without changing what the
     * PHP string evaluates to.
     *
     * Two characters make it unsafe. A double quote is ambiguous: it is the
     * character being introduced as the new delimiter, so re-delimiting around
     * it would need an escaping decision this sniff should not make on a
     * human's behalf.
     *
     * A backslash is a corruption risk, and the reason is that the value is
     * captured out of raw PHP source, not out of the evaluated string. In a
     * double-quoted PHP string `\'` is not an escape sequence — PHP keeps both
     * characters — so the capture can end on a backslash, which then lands
     * immediately in front of the injected `\"` closer and pairs with its
     * backslash instead. `"<a class='card\'>"` would become
     * `"<a class=\"card\\">"`: one literal backslash followed by a now-bare
     * quote that ends the string early, leaving the file unparseable.
     *
     * Bailing rather than counting backslash parity is deliberate — it is what
     * the sibling fixers in this standard already do (EscapeNestedQuotes
     * rejects `${\` outright, RequireStringInterpolation refuses a
     * single-quoted literal carrying any backslash), and the violation is still
     * reported for manual conversion either way.
     */
    private static function isSafeToConvert(string $value): bool
    {
        return strpbrk($value, '"\\') === false;
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
     * first one opens with a quote, so a continuation token is resolved by
     * walking back over the contiguous string tokens to that opener.
     *
     * The delimiter is read from the opener *only*, never from a fragment the
     * walk passes over. A fragment carries ordinary body text, and body text
     * can open with the very characters StringLiteral reads a delimiter from:
     * "B'day wishes" on a continuation line looks exactly like a binary-string
     * literal, and a line beginning with an apostrophe looks single-quoted.
     * Asking each fragment in turn and stopping at the first answer therefore
     * lets prose decide the delimiter for the whole literal, which flips the
     * escaping convention hasApostropheAttribute() searches for and silently
     * drops a real violation further down the string. Only the opener actually
     * holds the delimiter, so only the opener is asked.
     *
     * Index adjacency is what identifies a fragment: PHP has no syntax that
     * puts two separate literals in neighbouring token slots — an operator,
     * comma, or whitespace token always separates them — so a string token
     * immediately preceded by another is always a continuation of it.
     *
     * The opener is read through StringLiteral, not off the token's first
     * character, so a binary-string prefix (`B'<a class=\'x\'>'`) does not hide
     * the delimiter and send the whole literal down the double-quoted path. An
     * opener that yields no delimiter at all defaults to `"` — a single-quoted
     * literal cannot interpolate, so an interpolated string is always
     * double-quoted.
     */
    private function phpStringDelimiter(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $stackPtr;

        while ($opener > 0 && in_array($tokens[$opener - 1]['code'], self::STRING_TOKENS, true) === true) {
            $opener--;
        }

        return StringLiteral::delimiter($tokens[$opener]['content']) ?? '"';
    }
}
