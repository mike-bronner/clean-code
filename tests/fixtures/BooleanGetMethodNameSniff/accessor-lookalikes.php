<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Database\Eloquent\Model;

// Violation: the accessor name on a class that is not a model.
class Report
{
    public function getHasDetailsAttribute(): bool
    {
        return true;
    }
}

class Imported extends Model
{
    // Violation: a boolean getter on a model, without the Attribute suffix.
    public function getHasDetails(): bool
    {
        return true;
    }

    // Violation: get…Attribute with no name between the two parts.
    public function getAttribute(): bool
    {
        return true;
    }

    // Violation: the name after get starts lowercase.
    public function gethasOwnerAttribute(): bool
    {
        return true;
    }
}
