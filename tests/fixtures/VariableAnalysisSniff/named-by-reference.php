<?php

declare(strict_types=1);

namespace App;

class NamedByReference
{
    public function pregMatchLast(string $pattern, string $line): array
    {
        preg_match(pattern: $pattern, subject: $line, matches: $matches);

        return $matches;
    }

    public function pregMatchFirst(string $pattern, string $line): array
    {
        preg_match(matches: $matches, pattern: $pattern, subject: $line);

        return $matches;
    }

    public function pregMatchAllMiddle(string $pattern, string $text): array
    {
        preg_match_all(pattern: $pattern, matches: $found, subject: $text);

        return $found;
    }

    public function strReplaceLast(string $text): int
    {
        str_replace(search: 'a', replace: 'b', subject: $text, count: $count);

        return $count;
    }

    public function similarTextLast(string $left, string $right): float
    {
        similar_text(string1: $left, string2: $right, percent: $percent);

        return $percent;
    }

    public function parseStrFirst(string $query): array
    {
        parse_str(result: $result, string: $query);

        return $result;
    }

    public function mixedPositionalAndNamed(string $pattern, string $line): array
    {
        preg_match($pattern, $line, matches: $matches);

        return $matches;
    }

    public function fullyQualified(string $pattern, string $line): array
    {
        \preg_match(pattern: $pattern, subject: $line, matches: $matches);

        return $matches;
    }
}
