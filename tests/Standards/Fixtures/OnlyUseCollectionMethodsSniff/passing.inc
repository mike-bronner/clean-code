<?php

declare(strict_types=1);

use Illuminate\Support\Arr as RowCollection;
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

// A variable assigned inside a branch is not provably a Collection at the call
// site — the other branch may have run instead. Both orders are pinned: a
// verdict that depended on which assignment came last would flip between them.
function conditionalAssignmentsAreNotProvable(bool $useArray, array $rows): int
{
    if ($useArray) {
        $data = $rows;
    } else {
        $data = collect($rows);
    }

    if ($useArray) {
        $reversed = collect($rows);
    } else {
        $reversed = $rows;
    }

    return count($data) + count($reversed);
}

// A loop body may not run at all, so an assignment inside one proves nothing
// about the variable afterwards.
function loopAssignmentsAreNotProvable(array $rowSets): int
{
    $data = [];

    foreach ($rowSets as $rows) {
        $data = collect($rows);
    }

    return count($data);
}

// self::$x, static::$x and Example::$x all end in a variable token, but they
// write a property — never the local or parameter that shares its name.
class StaticPropertyWritesDoNotLeakIntoLocals
{
    private static Collection $items;

    public function fromSelf(array $rows, array $items): int
    {
        self::$items = collect($rows);

        return count($items);
    }

    public function fromStatic(array $rows, array $items): int
    {
        static::$items = Collection::make($rows);

        return count($items);
    }

    public function fromClassName(array $rows, array $items): int
    {
        StaticPropertyWritesDoNotLeakIntoLocals::$items = new Collection($rows);

        return count($items);
    }
}

// A method that hands back something other than a Collection ends the chain:
// a no-argument random() yields a single item, modelKeys() and mode() arrays.
function nonCollectionReturnsEndTheChain(Collection $collection): int
{
    return count($collection->random())
        + count($collection->modelKeys())
        + count($collection->mode());
}

// An alias resolves to the class it imports, so a Collection-suffixed alias on
// a class that is not one stays plain-array code.
function aliasedNonCollectionImportsAreNotCollections(array $rows): int
{
    return count(RowCollection::wrap($rows));
}

// A closure's use (...) list is a capture, not an import, and a captured
// variable's type is not provable inside the closure.
function closureCapturesAreNotImports(array $rows): callable
{
    $collection = collect($rows);

    return static function () use ($collection): int {
        return count($collection);
    };
}
