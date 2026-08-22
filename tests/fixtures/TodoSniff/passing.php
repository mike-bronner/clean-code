<?php

// The todos were all paid off before this shipped.
# A mastodon migration is not a marker.

/* XXX belongs to CleanCode.Commenting.DebtMarkers, so this sniff stays quiet */
/*
 * FIXME belongs to Generic.Commenting.Fixme, likewise.
 */

/**
 * An invoice that owes nothing.
 *
 * HACK is the fourth marker, and it is not this sniff's either.
 *
 * @param string $todoListName The list this invoice was drawn from.
 */
class Passing
{
    // The word autodocument carries it mid-word too.
    public function total(string $todoListName): int
    {
        return strlen($todoListName);
    }
}
