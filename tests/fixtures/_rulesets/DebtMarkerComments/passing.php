<?php

// A comment with no marker keyword in it at all.
# Another one, in the hash form.

/* And a single-line block comment carrying nothing to pay off. */
/*
 * A multi-line block comment, still with nothing to pay off.
 */

/**
 * A ledger whose debt was paid before it was written down.
 *
 * The nearest misses live one directory over, in each sniff's own
 * passing.php: this file is the plain negative case the four markers must
 * leave alone.
 */
class Passing
{
    public function total(): int
    {
        return 1;
    }
}
