<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
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
