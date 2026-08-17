<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Models\Account as Ledger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as CollectionAlias;

/**
 * Violations that only surface once an aliased import is followed. Reading the
 * types as written misses every one of them: `CollectionAlias` is not in the
 * collection list, and `Ledger` is not the model the method returns.
 *
 * Aliasing is realistic in exactly this domain — `Illuminate\Support\Collection`
 * and `Illuminate\Database\Eloquent\Collection` share a short name, and so do
 * models drawn from two namespaces.
 */
class Post extends Model
{
    // Resolves to Illuminate\Support\Collection: a collection, so it must be
    // prefixed `get`.
    public function allComments(): CollectionAlias
    {
        return new CollectionAlias();
    }

    // Resolves to App\Domain\Models\Account: a model, so it must be prefixed
    // `find`.
    public function fetchLedger(): Ledger
    {
        return new Ledger();
    }

    // Prefixed `find`, but named after the alias rather than the model it
    // actually returns. Judged as written this looks correct — "Ledger" is
    // right there in the name — which is exactly why it has to be resolved.
    public function findLedgerById(int $id): Ledger
    {
        return new Ledger();
    }
}
