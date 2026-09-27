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

    public function singleQuotedInnerAsDoubleQuoted(string $inner): string
    {
        $resolved = str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);

        return str_replace(['\\', "\"", '$'], ['\\\\', "\\\"", '\\$'], $resolved);
    }

    public function asHeredocBody(string $text): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $text);
    }

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
