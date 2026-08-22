<?php

namespace App\Models;

/**
 * A group `use` that mixes the three kinds of import PHP allows in one.
 *
 * `function` and `const` bind into their own symbol tables, so all three
 * members below may bind the name `Attribute` without colliding — the first is
 * the function `Attribute`, the second the constant `Attribute`, and only the
 * third is the class the cast resolves through.
 *
 * The two keyword members are written *ahead* of the class member on purpose.
 * Members are merged first-one-wins, so a reader that mistakes either of them
 * for a class import binds `attribute` to the wrong name and the real
 * `Casts\Attribute` behind it is discarded — and title() below, whose return
 * type is exactly that cast, stops being reported at all.
 */

use Illuminate\Database\Eloquent\{function makeAttribute as Attribute, const ATTRIBUTE_KEY as Attribute, Casts\Attribute};

class Book
{
    // Line 26 — the modern cast, resolved through the class member of the
    // group above.
    public function title(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }

    // Line 33 — not the cast. `makeAttribute` is a function import, so nothing
    // binds `MakeAttribute` as a class and the return type stays unresolved.
    public function subtitle(): MakeAttribute
    {
        return new MakeAttribute();
    }
}
