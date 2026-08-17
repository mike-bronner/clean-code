<?php

namespace App\Models;

/**
 * Unfinished source: a `function` keyword with no parameter list.
 *
 * getDeclarationName() bounds its search at the parenthesis opener, so with no
 * opener to stop at it runs on to the end of the file and hands back the name
 * of the *next* declaration — `getTitleAttribute` here, which would then be
 * reported against this line as well as its own. Only the accessor below is
 * reported.
 */
class Book
{
    public function ;

    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }
}
