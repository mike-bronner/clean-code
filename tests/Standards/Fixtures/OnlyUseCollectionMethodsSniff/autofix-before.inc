<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

function fixableGenericCalls(array $rows, Collection $collection): array
{
    $made = Collection::make($rows);

    return [
        count($collection),
        array_sum($made),
        count(collect($rows)->map(static fn (string $row): string => $row)),
        \count($collection),
        array_keys($collection),
        count($collection, COUNT_RECURSIVE),
        count($rows),
    ];
}
