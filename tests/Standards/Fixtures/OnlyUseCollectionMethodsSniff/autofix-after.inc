<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

function fixableGenericCalls(array $rows, Collection $collection): array
{
    $made = Collection::make($rows);

    return [
        $collection->count(),
        $made->sum(),
        collect($rows)->map(static fn (string $row): string => $row)->count(),
        $collection->count(),
        array_keys($collection),
        count($collection, COUNT_RECURSIVE),
        count($rows),
    ];
}
