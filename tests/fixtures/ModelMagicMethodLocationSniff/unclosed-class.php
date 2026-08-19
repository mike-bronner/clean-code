<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * A class the tokenizer never closed. It records no scope_closer, so the walk
 * has no end to stop at and the sniff passes over the class instead of
 * guessing where its body finishes.
 */
class Book extends Model
{
    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }
