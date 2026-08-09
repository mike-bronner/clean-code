<?php

/**
 * Compliant fixture for CleanCode.Functions.AvoidDuplicateFunctionBodies.
 *
 * Every declaration here is one the sniff registers on and walks. What keeps
 * the file silent is the comparison itself, not an absence of subject matter:
 *
 *   - Distinct bodies, well above the statement threshold.
 *   - Near misses that differ by exactly one token — a renamed variable, a
 *     changed literal, a swapped operator, one extra statement. Each near-miss
 *     body holds three statements, so the threshold is never what silences
 *     them; only the token difference is.
 *   - Identical bodies that sit below the threshold, which is what keeps
 *     boilerplate accessors and empty stubs quiet.
 *   - Identical closure bodies, which the sniff does not register on.
 *   - Bodyless declarations: an interface method and an abstract method.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\AvoidDuplicateFunctionBodiesSniff\Passing;

interface Formatter
{
    public function format(string $value): string;
}

abstract class BaseFormatter implements Formatter
{
    abstract public function format(string $value): string;
}

class Reports
{
    private string $name = 'reports';

    /**
     * Distinct bodies. Nothing here resembles anything else in the file.
     */
    public function monthly(array $rows): array
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += $row['amount'];
        }

        return ['total' => $total];
    }

    public function quarterly(array $rows): array
    {
        $names = array_column($rows, 'name');
        sort($names);

        return ['names' => $names];
    }

    /**
     * Near miss: a renamed local variable. Three statements each, so the
     * threshold is not what keeps them apart.
     */
    public function sumPrices(array $rows): int
    {
        $sum = 0;
        $sum += array_sum($rows);

        return $sum;
    }

    public function sumWeights(array $rows): int
    {
        $tally = 0;
        $tally += array_sum($rows);

        return $tally;
    }

    /**
     * Near miss: a changed literal.
     */
    public function withDefaultLimit(array $rows): array
    {
        $limit = 10;
        $rows = array_slice($rows, 0, $limit);

        return $rows;
    }

    public function withWideLimit(array $rows): array
    {
        $limit = 50;
        $rows = array_slice($rows, 0, $limit);

        return $rows;
    }

    /**
     * Near miss: a swapped operator.
     */
    public function grow(int $base): int
    {
        $value = $base;
        $value = $value + 2;

        return $value;
    }

    public function shrink(int $base): int
    {
        $value = $base;
        $value = $value - 2;

        return $value;
    }

    /**
     * Near miss: one extra statement, so one body is a strict prefix of the
     * other rather than an exact match.
     */
    public function shortWalk(array $rows): array
    {
        $rows = array_values($rows);
        $rows = array_unique($rows);

        return $rows;
    }

    public function longWalk(array $rows): array
    {
        $rows = array_values($rows);
        $rows = array_unique($rows);
        $rows = array_reverse($rows);

        return $rows;
    }

    /**
     * Identical bodies, two statements each — below the default threshold of
     * three, which is what keeps boilerplate accessors quiet.
     */
    public function name(): string
    {
        $value = $this->name;

        return $value;
    }

    public function label(): string
    {
        $value = $this->name;

        return $value;
    }

    /**
     * Identical empty bodies: no statements at all.
     */
    public function boot(): void
    {
    }

    public function shutdown(): void
    {
    }

    /**
     * Identical closure bodies, each well past the threshold, inside methods
     * whose own bodies differ. The sniff registers on named declarations only,
     * so neither the closures nor their differing hosts are reported.
     */
    public function eagerAdder(): callable
    {
        $offset = 1;

        return function (int $left, int $right) use ($offset): int {
            $result = $left + $right;
            $result += $offset;

            return $result;
        };
    }

    public function lazyAdder(): callable
    {
        $offset = 2;
        $label = 'lazy';
        unset($label);

        return function (int $left, int $right) use ($offset): int {
            $result = $left + $right;
            $result += $offset;

            return $result;
        };
    }
}
