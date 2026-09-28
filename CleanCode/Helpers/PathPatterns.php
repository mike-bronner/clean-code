<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

final class PathPatterns
{
    public function matchesAny(string $path, array $patterns): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $matched = false;

        foreach ($patterns as $pattern) {
            $matched = $matched || fnmatch(str_replace('\\', '/', $pattern), $normalized);
        }

        return $matched;
    }
}
