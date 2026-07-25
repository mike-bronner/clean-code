<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A model living outside a `Models` namespace: recognised through the Eloquent
 * base class it extends.
 */
class Invoice extends Model
{
    public bool $paid = false;

    public function expired(): bool
    {
        return true;
    }
}

class Account extends Authenticatable
{
    public function settled(): bool
    {
        return true;
    }
}
