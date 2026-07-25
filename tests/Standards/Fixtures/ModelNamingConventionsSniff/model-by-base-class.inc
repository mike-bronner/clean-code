<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Models living outside a `Models` namespace: recognised through the Eloquent
 * base class they extend. Every class here states its base through a different
 * spelling, and none of them can be matched without following the imports —
 * which is the point, since the base name as written is not a reliable signal
 * in either direction (see not-a-model.inc for the opposite direction).
 */
class Invoice extends Model
{
    public bool $paid = false;

    public function expired(): bool
    {
        return true;
    }
}

/**
 * The base is imported under an alias, so the name as written matches nothing.
 * Only resolution reaches `Illuminate\Database\Eloquent\Model`.
 */
class Receipt extends EloquentModel
{
    public bool $settled = false;

    public function voided(): bool
    {
        return true;
    }
}

/**
 * `Authenticatable` is a conventional alias, not a class name: it resolves to
 * `Illuminate\Foundation\Auth\User`. Matching the alias would be matching a
 * naming habit, and matching its resolved *short* name would look for `User`.
 * Only the fully qualified name identifies it.
 */
class Account extends Authenticatable
{
    public function locked(): bool
    {
        return true;
    }
}

/**
 * A fully qualified base needs no import at all, and the leading `\` must not
 * stop it being recognised.
 */
class Ledger extends \Illuminate\Database\Eloquent\Relations\Pivot
{
    public bool $reconciled = false;
}
