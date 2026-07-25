<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

function genericFunctionsOnAssignedCollections(array $rows): array
{
    $collection = collect($rows);

    return [
        array_map(static fn (array $row): string => $row['name'], $collection),
        array_filter($collection),
        array_reduce($collection, static fn (int $carry): int => $carry, 0),
        array_keys($collection),
        array_values($collection),
        count($collection),
        in_array('a', $collection, true),
        implode(', ', $collection),
    ];
}

function genericFunctionsOnConstructedCollections(array $rows): array
{
    $made = Collection::make($rows);
    $wrapped = Collection::wrap($rows);
    $constructed = new Collection($rows);

    return [
        array_sum($made),
        array_slice($wrapped, 0, 1),
        array_unique($constructed),
    ];
}

function genericFunctionsOnChainedCollections(Collection $collection, array $rows): array
{
    return [
        count($collection->filter(static fn (string $row): bool => $row !== '')),
        count(collect($rows)->map(static fn (string $row): string => $row)),
        array_merge($collection->values(), $rows),
    ];
}

function genericFunctionsOnQualifiedAndNullsafeCollections(Collection $collection): array
{
    return [
        \count($collection),
        array_values($collection?->filter(static fn (string $row): bool => $row !== '')),
    ];
}

// count()'s $mode argument changes the result, so the multi-argument form is
// reported but never auto-fixed.
function multiArgumentCallsAreReportedButNotFixable(Collection $collection): int
{
    return count($collection, COUNT_RECURSIVE);
}
