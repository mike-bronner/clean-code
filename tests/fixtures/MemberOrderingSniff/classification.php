<?php

/**
 * Which of the three method rules each method answers to.
 *
 * Every class here is silent when the classification is right, and every method
 * is positioned so that reading it as the *wrong* category puts it out of
 * alphabetical order and reports. The assertion is therefore the silence: this
 * fixture pins the classification, not the ordering.
 *
 * tests/Standards/MemberOrderingTest.php names the mutation each method catches.
 */

declare(strict_types=1);

/**
 * A relation return type is recognised however it is written — qualified,
 * nullable, one arm of a union, or the abstract base a project's own relation
 * class extends.
 */
class RelationReturnTypeModel extends Model
{
    public function zulu(): void
    {
    }

    public function alpha(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function bravo(): ?BelongsTo
    {
        return null;
    }

    public function charlie(): HasOne|MorphTo
    {
        return $this->hasOne(Profile::class);
    }

    public function delta(): Relation
    {
        return $this->hasMany(Comment::class);
    }
}

/**
 * The two shapes that look like a relationship and are not: a method with no
 * declared return type, where the relation is invisible to a token scan, and a
 * non-public method, which the standard's rule 3 does not cover.
 */
class NotARelationModel extends Model
{
    public function zulu(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function alpha()
    {
        return $this->hasMany(Comment::class);
    }

    protected function bravo(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

/**
 * Getters and setters, and the getter that is really a relationship: a public
 * method returning a relation answers to rule 3 whatever it is named.
 */
class AccessorModel extends Model
{
    public function zulu(): void
    {
    }

    public function getAlpha(): string
    {
        return '';
    }

    public function setBravo(string $value): void
    {
    }

    public function getCharlie(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

/**
 * `get` and `set` name an accessor only when a capital follows them. A method
 * that merely starts with those three letters is an ordinary method.
 */
class LowercasePrefixModel extends Model
{
    public function getZulu(): string
    {
        return '';
    }

    public function setZulu(string $value): void
    {
    }

    public function getter(): void
    {
    }

    public function settle(): void
    {
    }
}
