<?php

namespace App\Actions;

// A closed class whose last member is a bare `public function` with no name and
// no body — the shape an editor hands PHP_CodeSniffer mid-keystroke. There is
// no closing brace and no `;` to find, so the walk cannot resolve where that
// declaration ends and stops there.
//
// The two complete methods above it were read before that point, so `undo()` is
// still reported: the walk ended at the unreadable declaration rather than
// throwing away the class.
class PublishPost
{
    public function __invoke(Post $post): void
    {
    }

    public function undo(Post $post): void
    {
    }

    public function
}
