<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A concrete class whose only test sits in a split suite, at
 * tests/Unit/SplitTest.php. The shipped templates find it one suite folder
 * below the test root. A single custom template reports it, and the `tests/*`
 * root a split suite can configure finds it again.
 */
class Split
{
    public function value(): int
    {
        return 10;
    }
}
