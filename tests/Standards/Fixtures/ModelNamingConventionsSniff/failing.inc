<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public bool $published = false;

    protected bool $coverImage = false;

    // Opens with the letters of `is`, but `isolated` is one word — the prefix
    // has to start a word of its own to count.
    public bool $isolated = false;

    public function expired(): bool
    {
        return true;
    }

    // Same trap on the method side: `candidate` is not `can` + a condition.
    public function candidate(): bool
    {
        return true;
    }

    public function fetchUser(): User
    {
        return new User();
    }

    public function fetchOwner(): User|null
    {
        return null;
    }

    public function findByName(): User
    {
        return new User();
    }

    public function allComments(): Collection
    {
        return new Collection();
    }

    public function getTitleAttribute(): string
    {
        return 'title';
    }

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = $value;
    }
}
