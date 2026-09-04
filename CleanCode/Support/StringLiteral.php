<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

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

    private function body(string $content): string
    {
        return substr($content, strlen($this->prefix($content)));
    }
}
