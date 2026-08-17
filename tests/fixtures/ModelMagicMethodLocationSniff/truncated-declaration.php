<?php

namespace App\Models;

/**
 * Unfinished source, in the two shapes a declaration can be cut short in.
 *
 * Line 21 has no name at all: the token after `function` is the semicolon, so
 * the name test rejects it first — though the parenthesis test would reject it
 * a step later too. Line 29 has a name but no parameter list, which *only* the
 * parenthesis test rejects, and it is spelled as an accessor on purpose so that
 * a reader stopping at the name reports it.
 *
 * Both matter because getDeclarationName() bounds its search at the parenthesis
 * opener: with no opener to stop at it runs on to the end of the file and hands
 * back some later declaration's name. Only the finished accessor on line 23 is
 * reported.
 */
class Book
{
    public function ;

    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }

    // Line 29 — named, but cut off before the parameter list.
    public function getSubtitleAttribute
}
