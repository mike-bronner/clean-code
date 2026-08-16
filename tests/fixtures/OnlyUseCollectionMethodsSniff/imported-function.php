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
