<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain;
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

    // A root-anchored name that does resolve into a Models namespace is still
    // a model — the leading backslash is not a blanket exemption.
    public function fetchAdmin(): \App\Models\User
    {
        return new User();
    }

    // A qualified name resolves its first segment through the imports, so this
    // is App\Domain\Models\Account — a model, and unprefixed.
    public function loadAccount(): Domain\Models\Account
    {
        return new Domain\Models\Account();
    }

    // Promoted properties are declared in the parameter list, but they are
    // properties: the same yes/no rule applies. The plain $title parameter
    // beside them declares nothing and stays silent.
    public function __construct(
        private bool $archived = false,
        protected bool $flagged = false,
        string $title = '',
    ) {
        parent::__construct(['title' => $title]);
    }
}
