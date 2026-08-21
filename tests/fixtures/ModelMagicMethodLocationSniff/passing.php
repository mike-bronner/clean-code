<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * The compliant destination. Every shape the sniff flags in a class body sits
 * here in a trait, which is what the standard asks for.
 */
trait BookAttributes
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

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    #[Scope]
    public function draft($query)
    {
        return $query->whereNull('published_at');
    }
}

/**
 * An interface declares no body, so there is nothing to extract.
 */
interface Sellable
{
    public function getPriceAttribute($value);

    public function price(): Attribute;

    public function scopeOnSale($query);
}

/**
 * An enum is not an Eloquent model.
 */
enum Status: string
{
    case Draft = 'draft';

    public function getLabelAttribute($value)
    {
        return ucfirst($value);
    }

    public function badge(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    public function scopeVisible($query)
    {
        return $query;
    }
}

class Book extends Model
{
    use BookAttributes;

    /**
     * Eloquent's own accessors, overridden. Neither names an attribute, so
     * neither is the convention the standard is about.
     */
    public function getAttribute($key)
    {
        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $value);
    }

    public function getAttributes()
    {
        return parent::getAttributes();
    }

    public function getdescriptionattribute($value)
    {
        return $value;
    }

    public function scope()
    {
        return $this->scope;
    }

    public function scopes()
    {
        return [];
    }

    public function scoped($query)
    {
        return $query;
    }

    public function title(): string
    {
        return $this->attributes['title'];
    }

    /**
     * The Scope name here is an argument to another attribute, not an
     * attribute on this method. The comma in front of it is an argument
     * separator, so only the attribute group's own parenthesis depth
     * distinguishes the two.
     */
    #[Column(type: 'string', default: Scope::class)]
    public function featured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Declarations written inside a method body belong to that body, not to
     * this class.
     */
    public function build()
    {
        $anonymous = new class () {
            public function getTitleAttribute($value)
            {
                return $value;
            }

            public function scopePublished($query)
            {
                return $query;
            }
        };

        function scopeHelper($query)
        {
            return $query;
        }

        $closure = function (): Attribute {
            return Attribute::make(get: fn ($value) => $value);
        };

        $arrow = fn (): Attribute => Attribute::make(get: fn ($value) => $value);

        return [$anonymous, $closure, $arrow];
    }
}
