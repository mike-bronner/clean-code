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

// An arrow function's parameter is a new binding that shadows the enclosing
// name, in an arrow function exactly as in a closure. Both directions are
// pinned, because the leak ran both ways: a Collection parameter is a
// Collection only *inside* the arrow function, and an array parameter is an
// array inside it even when a Collection of that name is in scope outside.
class ArrowFunctionParametersShadowTheEnclosingScope
{
    public function collectionParameterDoesNotEscapeOutwards(array $items): int
    {
        $counter = static fn (Collection $items): int => $items->count();

        return count($items) + $counter(collect([]));
    }

    public function arrayParameterShadowsAnEnclosingCollection(array $rowSets): int
    {
        $rows = collect($rowSets);
        $counter = static fn (array $rows): int => count($rows);

        return $counter([]) + $rows->count();
    }
}

// The same shapes written as closures, which were always correct — they pin the
// behaviour the arrow-function cases above were brought into line with.
class ClosureParametersShadowTheEnclosingScope
{
    public function collectionParameterDoesNotEscapeOutwards(array $items): int
    {
        $counter = static function (Collection $items): int {
            return $items->count();
        };

        return count($items) + $counter(collect([]));
    }

    public function arrayParameterShadowsAnEnclosingCollection(array $rowSets): int
    {
        $rows = collect($rowSets);
        $counter = static function (array $rows): int {
            return count($rows);
        };

        return $counter([]) + $rows->count();
    }
}

// An assignment in an arrow function's body binds inside it and runs only when
// it is called, so it proves nothing about the scope around it.
function arrowBodyAssignmentsDoNotEscape(array $rows): int
{
    $data = [];
    $assign = static fn (): Collection => $data = collect($rows);

    return count($data) + $assign()->count();
}

// unlessEmpty()/unlessNotEmpty() are aliases of whenNotEmpty()/whenEmpty() and
// reduceWithKeys() delegates to reduce(), so they end the chain exactly as the
// methods they stand in for do. getOrPut() hands back the stored value.
function terminalAliasesEndTheChain(Collection $collection, callable $callback): int
{
    return count($collection->unlessEmpty($callback))
        + count($collection->unlessNotEmpty($callback))
        + count($collection->reduceWithKeys($callback, 0))
        + count($collection->getOrPut('total', 0));
}

// A binding is only as good as the constructs that can undo it. Everything
// below rebinds a tracked name through something that is not a plain
// assignment, so each one has to retire the name: by the time the generic call
// runs the variable holds something that is not a Collection. Left tracked,
// every one of these is a call phpcbf would rewrite into a runtime fatal.

function listDestructuringRetiresItsTargets(array $rows): int
{
    $data = collect($rows);
    [$data, $offset] = [$rows, 1];

    return count($data) + $offset;
}

function listConstructDestructuringRetiresItsTargets(array $rows): int
{
    $data = collect($rows);
    list($data, $offset) = [$rows, 1];

    return count($data) + $offset;
}

function foreachValueRetiresTheLoopVariable(array $groups): int
{
    $data = collect([]);
    $total = 0;

    foreach ($groups as $data) {
        $total += count($data);
    }

    return $total;
}

function foreachDestructuringRetiresEveryTarget(array $groups): int
{
    $data = collect([]);
    $total = 0;

    foreach ($groups as [$data, $weight]) {
        $total += count($data) + $weight;
    }

    return $total;
}

function foreachKeyRetiresTheKeyVariable(array $groups): int
{
    $data = collect([]);
    $total = 0;

    foreach ($groups as $data => $weight) {
        $total += count($data) + $weight;
    }

    return $total;
}

function catchRetiresTheCaughtVariable(array $rows): int
{
    $data = collect($rows);

    try {
        throw new RuntimeException('failed');
    } catch (RuntimeException $data) {
        return count($data->getMessage());
    }
}

// The closure body rebinds the captured name, and when it runs is not knowable
// from the tokens — the `&` in the capture list is the whole proof needed.
function referenceCaptureRetiresTheCapturedName(array $rows): int
{
    $data = collect($rows);
    $mutate = static function () use (&$data): void {
        $data = [1, 2, 3];
    };
    $mutate();

    return count($data);
}

// `global` and `static` both rebind an already-assigned local: the name stops
// referring to the Collection and starts referring to the global or the
// function's static.
function globalRebindsTheName(array $rows): int
{
    $data = collect($rows);
    global $data;

    return count($data);
}

function staticRebindsTheName(array $rows): int
{
    $data = collect($rows);
    static $data;

    return count($data);
}

// A compound assignment combines the name's own prior value with something
// else, so it never states outright that the name still holds a Collection.
function compoundAssignmentRetiresTheName(array $rows): int
{
    $data = collect($rows);
    $data .= 'suffix';

    return count($data);
}

// `??=` is the compound assignment that looks most like a proof — the
// right-hand side really is a Collection — but whether it ran at all depends on
// the name's prior value, which is exactly what the sniff cannot see. Retiring
// here is a deliberate false negative, and the safe direction to miss in.
function coalescingAssignmentRetiresTheName(array $rows, $data): int
{
    $data ??= collect($rows);

    return count($data);
}

// An index write is a target the sniff cannot fully parse, so it retires rather
// than steps over. The container is in fact still a Collection here, so this is
// another deliberate false negative — the price of never stepping over a write.
function indexWriteRetiresTheContainer(array $rows): int
{
    $data = collect($rows);
    $data[] = 'extra';

    return count($data);
}

// Retirement is sticky: this call sits *before* the assignment that makes the
// name a Collection, so judging the name by the file's last write would flag —
// and rewrite — a call that operates on the array.
function retirementAppliesToCallsBeforeTheCollectionAssignment(array $rows): int
{
    $data = $rows;
    $total = count($data);
    $data = collect($rows);

    return $total + $data->count();
}

// A name copied out of one that another construct retires cannot inherit a
// binding that construct invalidated.
function copiesDoNotInheritARetiredBinding(array $groups, array $rows): int
{
    $data = collect($rows);

    foreach ($groups as $data) {
        $ignored = $data;
    }

    $copy = $data;

    return count($copy) + count($ignored);
}
