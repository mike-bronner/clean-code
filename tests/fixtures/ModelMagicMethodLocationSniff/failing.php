<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model as Eloquent, Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\{Casts\Attribute as GroupedAttribute, Attributes\Scope};

class Book extends Eloquent
{
    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = strtolower($value);
    }

    public function author(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    public function publisher(): CastAttribute
    {
        return CastAttribute::make(get: fn ($value) => $value);
    }

    public function isbn(): ?\Illuminate\Database\Eloquent\Casts\Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    public function edition(): Attribute|null
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    public function summary(): (\Countable&\Stringable)|Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    public function cover(): GroupedAttribute
    {
        return GroupedAttribute::make(get: fn ($value) => $value);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    #[Scope]
    public function draft($query)
    {
        return $query->whereNull('published_at');
    }

    #[Scope]
    #[Deprecated]
    protected function archived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    #[Column(Scope::class), Scope]
    public static function featured($query)
    {
        return $query->where('featured', true);
    }
}

abstract class Publication extends Eloquent
{
    abstract public function getBlurbAttribute();

    abstract public function scopeRecent($query);
}
