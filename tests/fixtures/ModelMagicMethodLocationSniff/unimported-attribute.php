<?php

namespace App\Models;

use App\Support\Attribute as LocalAttribute;

/**
 * `Attribute` here resolves to App\Models\Attribute, not Laravel's cast: the
 * file imports no Illuminate cast, so a bare return type of that name is this
 * namespace's own class. The aliased import names a different class of the
 * same short name, which must not resolve either — the alias is what an import
 * binds, and the aliased name is the one a type has to use.
 */
class Book
{
    public function author(): Attribute
    {
        return new Attribute();
    }

    public function publisher(): LocalAttribute
    {
        return new LocalAttribute();
    }

    public function isbn(): \App\Support\Attribute
    {
        return new LocalAttribute();
    }
}
