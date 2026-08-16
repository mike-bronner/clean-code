<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ValueObject as Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as CollectionAlias;

/**
 * The costly direction of the same invariant: correct code that identifying a
 * symbol on its source casing would report, with advice that makes no sense.
 * Under a `Models` namespace, a mis-cased alias that misses the import map falls
 * through to namespace-qualification — which manufactures a name carrying a
 * `Models` segment, so the sniff decides it is looking at a model and invents a
 * violation against code that is already right.
 */
class Post extends Model
{
    /**
     * `money` is the alias `Money`, a plain value object — no model, nothing to
     * check. Missed, it becomes App\Models\money: a "model", so the `find`
     * prefix is satisfied but the name is told to spell out "money".
     */
    public function findBar(): money
    {
        return new Money();
    }

    /**
     * `collectionalias` is the alias `CollectionAlias`, a collection, correctly
     * prefixed `get`. Missed, it becomes App\Models\collectionalias — read as a
     * single model, demanding the `find` prefix on a method that returns many.
     */
    public function getCommentsByType(string $type): collectionalias
    {
        return new CollectionAlias();
    }

    /**
     * A real override of Eloquent's `newCollection()`, which PHP dispatches
     * case-insensitively. The exemption has to fold case too: flagged, the
     * advice is to rename the method, which silently breaks the override and
     * reverts Eloquent to its default collection.
     */
    public function newcollection(array $models = []): Collection
    {
        return new Collection($models);
    }
}
