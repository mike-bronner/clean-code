<?php

declare(strict_types=1);

interface Formatter
{
    public function format(string $value): string;
}

final class UpperFormatter implements Formatter
{
    public function format(string $value): string
    {
        return strtoupper($value);
    }
}

final class LowerFormatter implements Formatter
{
    public function format(string $value): string
    {
        return strtolower($value);
    }
}

final class Report
{
    /**
     * Polymorphism replaces the branch entirely.
     */
    public function render(Formatter $formatter, string $value): string
    {
        return $formatter->format($value);
    }

    /**
     * A mapping array replaces an if/elseif chain.
     */
    public function label(string $status): string
    {
        $labels = [
            'open' => 'Open',
            'closed' => 'Closed',
            'merged' => 'Merged',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    /**
     * match replaces a switch. Its arms use T_MATCH_ARROW, and its `default`
     * is a T_DEFAULT — neither is a token this sniff registers on.
     */
    public function icon(string $status): string
    {
        return match ($status) {
            'open' => 'circle',
            'closed', 'merged' => 'check',
            default => 'question',
        };
    }

    /**
     * The null-default and null-safe operators collapse a branch into an
     * expression. `?string` is a nullable type hint, not a ternary.
     */
    public function author(?Report $parent): ?string
    {
        $name = $parent?->cachedName;
        $name ??= 'unknown';

        return $name;
    }

    public ?string $cachedName = null;

    public function shout(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper($value);
    }

    /**
     * Iteration and error handling are other standards' territory.
     */
    public function totals(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += $row;
        }

        while ($total > 100) {
            $total -= 100;
        }

        for ($index = 0; $index < 3; $index++) {
            $total += $index;
        }

        do {
            $total--;
        } while ($total > 0);

        try {
            $total += random_int(0, 1);
        } catch (Throwable $error) {
            $total = 0;
        }

        return $total;
    }
}
