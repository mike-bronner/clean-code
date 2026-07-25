<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Collection as Coll;

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

// The remaining mapped functions, so every entry in the mapping table is
// pinned — the argument-order-reversed ones (array_key_exists => has,
// array_search => search) most of all.
function remainingMappedFunctionsAreFlagged(Collection $collection, array $rows): array
{
    return [
        array_diff($collection, $rows),
        array_intersect($collection, $rows),
        array_key_exists('a', $collection),
        array_search('a', $collection, true),
        join(', ', $collection),
    ];
}

// An aliased import is judged by the class it names, not by the alias.
function aliasedCollectionImportsAreFlagged(Coll $collection, array $rows): array
{
    $made = Coll::make($rows);

    return [
        count($collection),
        count($made),
    ];
}

// Shadowing cuts both ways: inside the arrow function its own Collection
// parameter *is* a Collection, and a name it does not declare still resolves to
// the enclosing scope's Collection by capture.
function arrowFunctionParametersBindInsideTheArrowFunction(Collection $outer): array
{
    return [
        static fn (Collection $rows): int => count($rows),
        static fn (): int => count($outer),
    ];
}

// A chain is typed through TERMINAL_METHODS, which is a curated mirror of a
// framework API — good enough to report on, never good enough to rewrite. These
// stay reported and unfixable however the list drifts.
function chainedReceiversAreReportedButNeverFixable(Collection $collection, array $rows): array
{
    return [
        count($collection->filter(static fn (string $row): bool => $row !== '')),
        count(collect($rows)->map(static fn (string $row): string => $row)),
    ];
}

// PHP lets any callee rebind a caller's variable through a `&$parameter`, and
// neither a userland signature nor the by-reference builtins are knowable from
// this file's tokens. Mirroring PHP's by-reference builtins would be another
// moving third-party API whose every omission is a false positive, so a bare
// variable handed to any other call is instead treated as no longer provably
// unmutated: still reported, never rewritten. The severity collapses rather
// than relocating.
function byReferenceArgumentsAreReportedButNeverFixable(Collection $collection, string $subject): array
{
    receivesByReference($collection);
    preg_match('/[a-z]+/', $subject, $collection);

    return [
        count($collection),
        array_sum($collection),
    ];
}

function receivesByReference(mixed &$target): void
{
    $target = [1, 2, 3];
}

// Retirement has to be aimed at the names a construct actually binds. The three
// below sit next to a construct that retires *something*, and must survive it:
// each is still a Collection at the call, so each stays reported and fixable.
// Retiring too broadly would silence them, which no error count would reveal.

// A foreach binds the names after `as`, and reads the subject. Iterating a
// Collection must not retire the Collection.
function foreachSubjectKeepsItsTracking(Collection $data): int
{
    $total = 0;

    foreach ($data as $row) {
        $total += (int) $row;
    }

    return $total + count($data);
}

// A by-value capture binds a copy inside the closure and leaves the outer name
// alone; only `&` rebinds it.
function valueCaptureLeavesTheOuterNameAlone(Collection $data): int
{
    $read = static function () use ($data): int {
        return $data->count();
    };

    return $read() + count($data);
}

// A declaration's parameter list is not a call, so an untyped parameter later
// assigned a Collection is not treated as having escaped by reference.
function untypedParameterAssignedACollectionStaysFixable(array $rows, $data): int
{
    $data = collect($rows);

    return count($data);
}

// A declaration's parameter list is not a call. Treated as one, the parameter
// name below would register as having escaped by reference — and because a
// parameter token resolves to the *file* scope rather than the function's, it
// would land in the same bucket as the file-scope Collection of that name and
// quietly cost it its autofix.
function declaresAParameterSharingAFileScopeName($tally): void
{
    unset($tally);
}

$tally = collect([1, 2]);
$fileScopeTotal = count($tally);
