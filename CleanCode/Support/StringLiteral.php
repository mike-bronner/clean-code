<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Util\Tokens;

final class StringLiteral
{
    public function prefix(string $content): string
    {
        $first = substr($content, 0, 1);

        return ($first === 'b' || $first === 'B') ? $first : '';
    }

    public function delimiter(string $content): ?string
    {
        $opener = substr($this->body($content), 0, 1);

        return ($opener === "\"" || $opener === "'") ? $opener : null;
    }

    public function isComplete(string $content): bool
    {
        $body = $this->body($content);
        $delimiter = $this->delimiter($content);

        return $delimiter !== null && strlen($body) >= 2 && substr($body, -1) === $delimiter;
    }

    public function inner(string $content): string
    {
        return substr($this->body($content), 1, -1);
    }

    // The text a run of concatenated string literals builds, starting at
    // $start, with each fragment's escapes resolved the way its own delimiter
    // resolves them. A `\n` is a line break inside double quotes and two
    // characters inside single quotes, and a caller reading the text for its
    // shape has to see the difference — a config block written with "\n" is one
    // block, and a Windows path written with '\n' is not.
    public function concatenated(array $tokens, int $start): string
    {
        $value = '';
        $ptr = $start;
        $end = count($tokens);

        while ($ptr < $end) {
            if ($this->isLiteral($tokens, $ptr) === true) {
                $value .= $this->resolveEscapes($tokens[$ptr]['content']);
                $ptr++;

                continue;
            }

            if (
                $tokens[$ptr]['code'] !== T_STRING_CONCAT
                && isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === false
            ) {
                break;
            }

            $ptr++;
        }

        return $value;
    }

    // A single-quoted literal's inner text, rewritten to sit inside double
    // quotes with its value unchanged.
    //
    // Two conversions, in this order. A single-quoted body resolves only `\\`
    // and `\'`, so those come back to the characters they stand for first;
    // every other backslash in it was already literal. Then the whole thing is
    // escaped for a double-quoted body, where a backslash, a quote and a `$`
    // each mean something.
    //
    // Escaping `$` covers the brace triggers too: `{$` becomes `{\$` and `${`
    // becomes `\${`, neither of which interpolates, so a lone `{` needs nothing.
    public function singleQuotedInnerAsDoubleQuoted(string $inner): string
    {
        $resolved = str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);

        return str_replace(['\\', "\"", '$'], ['\\\\', "\\\"", '\\$'], $resolved);
    }

    // Literal text rewritten to sit in a HEREDOC body with its value unchanged.
    //
    // A HEREDOC resolves the same escapes a double-quoted string does and
    // interpolates the same expressions, so a backslash and a `$` each have to
    // be escaped. Escaping `$` covers `{$` and `${` too, which is why a lone
    // brace needs nothing. A `"` needs nothing either — that is the readability
    // gain a HEREDOC has over both quoted forms.
    public function asHeredocBody(string $text): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $text);
    }

    // Only the whitespace escapes, and only where the delimiter resolves them.
    // Nothing else matters to a caller reading the text for its shape, and
    // resolving more would mean reimplementing PHP's own unescaping.
    private function resolveEscapes(string $content): string
    {
        $inner = $this->inner($content);

        if ($this->delimiter($content) !== "\"") {
            return $inner;
        }

        return str_replace(['\\r\\n', '\\n', '\\r', '\\t'], ["\n", "\n", "\r", "\t"], $inner);
    }

    private function isLiteral(array $tokens, int $ptr): bool
    {
        return isset($tokens[$ptr]) === true
            && in_array(
                $tokens[$ptr]['code'],
                [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING],
                true
            ) === true;
    }

    private function body(string $content): string
    {
        return substr($content, strlen($this->prefix($content)));
    }
}
