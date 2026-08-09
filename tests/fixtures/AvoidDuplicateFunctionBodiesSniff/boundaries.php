<?php

/**
 * Threshold fixture for CleanCode.Functions.AvoidDuplicateFunctionBodies.
 *
 * Five identical pairs, each pinned at a different statement count, so the
 * $minimumStatements cut can be located exactly rather than inferred from two
 * extremes:
 *
 *   Pair E — 0 statements (empty bodies)
 *   Pair F — 1 statement
 *   Pair A — 2 statements, one below the default of 3
 *   Pair C — 2 statements, but 4 semicolons: the other two punctuate a `for`
 *            header, which the statement count excludes. Counting them would
 *            put this pair at 4 and fire it at the default threshold.
 *   Pair B — 3 statements, exactly the default
 *
 * Nothing else in the file is duplicated, so every warning here is a
 * threshold decision.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\AvoidDuplicateFunctionBodiesSniff\Boundaries;

class Thresholds
{
    public function emptyOne(): void
    {
    }

    public function emptyTwo(): void
    {
    }

    public function singleOne(array $rows): int
    {
        return count($rows);
    }

    public function singleTwo(array $rows): int
    {
        return count($rows);
    }

    public function pairOne(array $rows): array
    {
        $rows = array_values($rows);

        return $rows;
    }

    public function pairTwo(array $rows): array
    {
        $rows = array_values($rows);

        return $rows;
    }

    public function loopOne(int $bound): int
    {
        for ($index = 0; $index < $bound; $index++) {
            $total = $index;
        }

        return $total ?? 0;
    }

    public function loopTwo(int $bound): int
    {
        for ($index = 0; $index < $bound; $index++) {
            $total = $index;
        }

        return $total ?? 0;
    }

    public function tripleOne(array $rows): array
    {
        $rows = array_values($rows);
        $rows = array_unique($rows);

        return $rows;
    }

    public function tripleTwo(array $rows): array
    {
        $rows = array_values($rows);
        $rows = array_unique($rows);

        return $rows;
    }
}
