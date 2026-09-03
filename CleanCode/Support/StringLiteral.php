<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

final class StringLiteral
{
    public static function prefix(string $content): string
    {
        $first = substr($content, 0, 1);

        return ($first === 'b' || $first === 'B') ? $first : '';
    }

    public static function delimiter(string $content): ?string
    {
        $opener = substr(self::body($content), 0, 1);

        return ($opener === "\"" || $opener === "'") ? $opener : null;
    }

    public static function isComplete(string $content): bool
    {
        $body = self::body($content);
        $delimiter = self::delimiter($content);

        return $delimiter !== null && strlen($body) >= 2 && substr($body, -1) === $delimiter;
    }

    public static function inner(string $content): string
    {
        return substr(self::body($content), 1, -1);
    }

    private static function body(string $content): string
    {
        return substr($content, strlen(self::prefix($content)));
    }
}
