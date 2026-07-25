<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    public bool $isPublished = false;

    protected bool $hasCoverImage = false;

    public bool $shouldQueue = false;

    // Not a boolean, so the question-prefix rule does not apply.
    public string $isbn = '';

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function hasExpired(): bool
    {
        return true;
    }

    public function shouldQueue(): bool
    {
        return $this->shouldQueue;
    }

    public function findUserByName(string $name): User
    {
        return new User();
    }

    // Nullable single-model return, still a `find`.
    public function findUserById(int $id): ?User
    {
        return null;
    }

    // `self` names no model, so the prefix alone is required.
    public function findLatest(): self
    {
        return $this;
    }

    public function getUsersByType(string $type): Collection
    {
        return new Collection();
    }

    // Relationship methods return a relation, not a model or a collection.
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // The "new" attribute implementation, exposing a related model's property.
    public function authorName(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->author->name);
    }

    public function __construct()
    {
        parent::__construct();
    }
}
