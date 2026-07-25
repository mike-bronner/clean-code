<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Collection as Coll;

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

function aliasedImportsAreRewritten(array $rows): int
{
    $made = Coll::make($rows);

    return count($made);
}

// The cases below are the ones a wrong verdict would rewrite into a runtime
// fatal, so the fixer has to leave them byte-for-byte alone.

function conditionalAssignmentsAreNeverRewritten(bool $useArray, array $rows): int
{
    if ($useArray) {
        $data = $rows;
    } else {
        $data = collect($rows);
    }

    return count($data);
}

class StaticPropertyWritesAreNeverRewritten
{
    private static Collection $items;

    public function run(array $rows, array $items): int
    {
        self::$items = collect($rows);

        return count($items);
    }
}

function terminalMethodsAreNeverRewritten(Collection $collection): int
{
    return count($collection->random());
}

function arrowParametersAreNeverRewritten(array $items, array $rowSets): int
{
    $counter = static fn (Collection $items): int => $items->count();
    $rows = collect($rowSets);
    $shadowed = static fn (array $rows): int => count($rows);

    return count($items) + $counter(collect([])) + $shadowed([]) + $rows->count();
}

// A chained receiver's type comes from TERMINAL_METHODS, so the fixer declines
// it however complete that list happens to be.
function chainedReceiversAreNeverRewritten(Collection $collection, callable $callback): int
{
    return count($collection->filter($callback)) + count($collection->unlessEmpty($callback));
}
