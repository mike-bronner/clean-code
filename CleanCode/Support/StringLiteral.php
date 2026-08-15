<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

/**
 * Reads the shape of a PHP string-literal token for the Strings standard
 * sniffs.
 *
 * Every one of those sniffs has to answer the same two questions about a
 * literal token before it can rewrite it, and getting either wrong corrupts
 * code:
 *
 * - **Which quote delimits it?** Not simply the token's first character. A
 *   binary-string prefix (`b'x'`, `B"y"`) sits in front of the delimiter, and
 *   PHP_CodeSniffer's tokenizer splits the *lowercase* `b` off into a separate
 *   `T_BINARY_CAST` token while an uppercase `B` stays inside the literal's
 *   content. A sniff reading `$content[0]` therefore sees `B` and treats the
 *   literal as single-quoted when it is double-quoted (or skips it entirely).
 *   Uppercase is the only spelling that reaches this class: prefix() reads
 *   either for symmetry, but its lowercase branch is unreachable for genuine
 *   tokenizer output. Round-trip safety for `b` comes instead from a fixer
 *   replacing only the string token, which leaves the cast token in place.
 * - **Is this the whole literal?** A string whose source spans several physical
 *   lines is tokenized one token per line, all of the same token type. Only the
 *   first fragment opens with the delimiter and only the last one closes with
 *   it, so a fixer that treats a fragment as a whole literal rewrites a piece
 *   of a string and leaves the file unparseable.
 */
final class StringLiteral
{
    /**
     * The binary-string prefix on a literal token, or '' when it has none.
     */
    public static function prefix(string $content): string
    {
        $first = substr($content, 0, 1);

        return ($first === 'b' || $first === 'B') ? $first : '';
    }

    /**
     * The quote character that delimits a literal token, or null when the
     * token does not open one — which is what every fragment of a multi-line
     * literal after the first looks like.
     */
    public static function delimiter(string $content): ?string
    {
        $opener = substr(self::body($content), 0, 1);

        return ($opener === '"' || $opener === "'") ? $opener : null;
    }

    /**
     * Whether a token holds a whole literal rather than one physical-line
     * fragment of a multi-line one: it opens with a delimiter and closes with
     * the same one. The length check keeps a lone delimiter from counting as
     * both ends of itself.
     */
    public static function isComplete(string $content): bool
    {
        $body = self::body($content);
        $delimiter = self::delimiter($content);

        return $delimiter !== null && strlen($body) >= 2 && substr($body, -1) === $delimiter;
    }

    /**
     * A complete literal's inner text, without its prefix or its delimiters.
     * Callers are expected to have established isComplete() first.
     */
    public static function inner(string $content): string
    {
        return substr(self::body($content), 1, -1);
    }

    /**
     * The token's content with any binary-string prefix removed, so the
     * delimiter is the first character.
     */
    private static function body(string $content): string
    {
        return substr($content, strlen(self::prefix($content)));
    }
}
