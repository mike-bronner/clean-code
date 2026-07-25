<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

function collectionMethodsOnly(array $rows): int
{
    $collection = collect($rows);
    $names = $collection->map(static fn (array $row): string => $row['name']);
    $active = $names->filter(static fn (string $name): bool => $name !== '');

    return $active->reduce(static fn (int $carry): int => $carry + 1, 0) + $active->count();
}

function constructedCollectionsUseCollectionMethods(array $rows): bool
{
    $made = Collection::make($rows);
    $wrapped = Collection::wrap($rows);
    $constructed = new Collection($rows);

    return $made->contains('a')
        && $wrapped->keys()->isNotEmpty()
        && $constructed->values()->isEmpty();
}

function plainArraysStayOnGenericFunctions(array $rows): int
{
    $names = array_map(static fn (array $row): string => $row['name'], $rows);
    $active = array_filter($names);

    return count($active) + count(array_keys($rows));
}

// toArray()/all() hand back plain arrays, so the generic calls wrapping them
// are array code, not Collection code.
function escapeHatchesReturnPlainArrays(Collection $collection): int
{
    $items = $collection->toArray();

    return count($items) + count($collection->all());
}

function collectionsAreScopedToTheirFunction(array $rows): Collection
{
    $scoped = collect($rows);

    return $scoped->values();
}

function theSameNameElsewhereIsAPlainArray(array $scoped): int
{
    return count($scoped);
}

function reassignedVariablesAreRetired(array $rows): int
{
    $values = collect($rows);
    $values = $rows;

    return count($values);
}

// A property's type is not provable from the tokens, so it is left alone.
function propertiesAreNotGuessedAt(Collection $collection): int
{
    return count($collection->items);
}

// Same-named methods and namespaced functions are different callables, so the
// generic-function rule never reaches them.
function sameNamedCallablesAreNotGenericFunctions(Collection $collection, object $helper): int
{
    return $helper->count($collection)
        + $helper?->count($collection)
        + Helper::count($collection)
        + Helper\count($collection);
}

// Only the global collect() helper, a Collection-named class, and its make()/
// wrap() factories construct a Collection; look-alikes do not.
function lookAlikeConstructorsAreNotCollections(array $rows): int
{
    $namespaced = Support\collect($rows);
    $other = new ArrayObject($rows);
    $notAFactory = Collection::fromArray($rows);

    return count($namespaced) + count($other) + count($notAFactory);
}

// An offset read hands back an element, not the Collection.
function offsetReadsAreNotCollections(Collection $collection): int
{
    return count($collection['rows']);
}
