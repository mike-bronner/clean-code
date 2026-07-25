<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Collection as Coll;

function fixableGenericCalls(array $rows, Collection $collection): array
{
    $made = Collection::make($rows);

    return [
        $collection->count(),
        $made->sum(),
        count(collect($rows)->map(static fn (string $row): string => $row)),
        $collection->count(),
        array_keys($collection),
        count($collection, COUNT_RECURSIVE),
        count($rows),
    ];
}

function aliasedImportsAreRewritten(array $rows): int
{
    $made = Coll::make($rows);

    return $made->count();
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

// Every rebinding construct below leaves the name holding something that is not
// a Collection, so phpcbf must leave all of these byte-for-byte alone. Rewritten,
// each one is a runtime fatal rather than a style change.
function rebindingConstructsAreNeverRewritten(array $groups, array $rows, string $subject): int
{
    $data = collect($rows);
    [$data, $offset] = [$rows, 1];
    $total = count($data) + $offset;

    foreach ($groups as $data) {
        $total += count($data);
    }

    try {
        throw new RuntimeException('failed');
    } catch (RuntimeException $data) {
        $total += (int) $data->getCode();
    }

    $captured = collect($rows);
    $mutate = static function () use (&$captured): void {
        $captured = [1, 2, 3];
    };
    $mutate();

    $escaped = collect($rows);
    preg_match('/[a-z]+/', $subject, $escaped);

    return $total + count($captured) + count($escaped);
}
