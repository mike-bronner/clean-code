<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Conditionals.AvoidConditionals.
 *
 * There is no "compliant if" to show here: the sniff reports *every* if,
 * elseif, ternary and switch, so a conforming file is one that reached the
 * same behaviour without a branch. What this fixture therefore carries is the
 * two things that keep it discriminating:
 *
 *   1. The recommended replacements the standard points at — polymorphism,
 *      a mapping array, match, a null-default operator.
 *   2. The near-miss token shapes the sniff must stay silent on. Every one is
 *      punctuation PHP_CodeSniffer has, at some version, tokenized close to
 *      T_INLINE_THEN or T_IF: `??`, `??=`, `?->`, a `?string` nullable type
 *      hint, `match` arms, `case`/`default` inside a match, `catch`, and each
 *      loop form. A regression that widened register() to any of these would
 *      redden this file.
 */

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
