<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A concrete class whose only test sits in a split suite, at
 * tests/Unit/SplitTest.php. Both halves are asserted from this one file: it
 * reports under the shipped `tests` test root, and falls silent under the
 * `tests/*` root a split suite configures — the wildcard is what keeps a
 * non-mirrored layout down to a single lookup.
 */
class Split
{
    public function value(): int
    {
        return 10;
    }
}
