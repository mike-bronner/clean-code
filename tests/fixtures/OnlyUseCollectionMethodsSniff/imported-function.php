<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use Illuminate\Support\Collection;

use function MikeBronner\CleanCode\Support\countDistinctTags as count;
use function MikeBronner\CleanCode\Support\implodeWithOxfordComma as implode;

/**
 * A `use function … as count;` import rebinds the name for the whole file, so
 * an unqualified `count($collection)` calls the import rather than the builtin
 * the sniff maps. Rewriting it to `$collection->count()` would not fatal — it
 * would quietly return a different number, which is worse.
 *
 * This lives in a fixture of its own because the shadow is file-wide: dropped
 * into passing.inc it would silence every other `count()` there and make those
 * assertions vacuous.
 */
function importedFunctionsShadowTheBuiltin(Collection $collection): string
{
    return implode($collection) . count($collection);
}

/**
 * The import binds the *unqualified* name only. A leading separator pins the
 * call to the global function, so it is the builtin again and stays reportable.
 */
function fullyQualifiedCallsAreStillTheBuiltin(Collection $collection): int
{
    return \count($collection);
}

/**
 * The builtin `count()` takes its argument by value, which is why handing a
 * Collection to it does not cost the receiver its proven type. The *import*
 * carries no such promise: countDistinctTags() may declare `&$items` and hand
 * back a rebound variable, so `$collection` stops being provably a Collection
 * from here on.
 *
 * The `array_sum()` below is therefore still reported — the sniff never stops
 * suspecting the variable — but no longer rewritten, because the receiver is
 * only proven at its type hint and not at the call site.
 */
function aShadowedCallMayRebindItsArgument(Collection $collection): int
{
    count($collection);

    return array_sum($collection);
}

/**
 * A method merely spelled like one of the seventeen is userland code too, and
 * escapes its argument for the same reason. Pinned here rather than in
 * passing.php because it is the same guard, read at the same call site.
 */
function aMethodOfTheSameNameMayRebindItToo(Collection $collection, Aggregator $aggregator): int
{
    $aggregator->count($collection);

    return array_sum($collection);
}
