<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;

// Not a violation: Laravel needs the get{Name}Attribute name to expose
// $model->hasDetails, for every boolean return form the sniff reads.
class Imported extends Model
{
    public function getHasDetailsAttribute(): bool
    {
        return true;
    }

    public function getIsVisibleAttribute(): ?bool
    {
        return null;
    }

    /**
     * @return bool
     */
    public function getIsDraftAttribute()
    {
        return false;
    }
}

class Aliased extends Eloquent
{
    public function getIsOpenAttribute(): bool
    {
        return true;
    }
}

class Qualified extends \Illuminate\Database\Eloquent\Model
{
    public function getHasOwnerAttribute(): bool
    {
        return true;
    }
}
