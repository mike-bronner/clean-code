<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\ValueObject as Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * Neither declared under a `Models` namespace segment nor extending an
 * Eloquent base class, so none of the model naming rules apply here.
 */
class UserService
{
    public bool $published = false;

    public function expired(): bool
    {
        return true;
    }

    public function fetchUser(): User
    {
        return new User();
    }

    public function allUsers(): Collection
    {
        return new Collection();
    }

    public function getTitleAttribute(): string
    {
        return 'title';
    }
}

/**
 * Extends something *called* `Model`, which resolves to App\Support\ValueObject
 * — a plain value object. Projects that own a `Model` class alias Eloquent's out
 * of the way exactly like this, so the name as written is worthless here: taken
 * at face value it makes every declaration below a model's.
 */
class Money extends Model
{
    public bool $rounded = false;

    public function negative(): bool
    {
        return false;
    }
}
