<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A concrete class sitting directly on the source root, with the companion
 * test the default mapping asks for at tests/CoveredTest.php. `{path}` resolves
 * to nothing here, which is the case that would produce a doubled separator if
 * the empty segment were not dropped.
 */
class Covered
{
    public function value(): int
    {
        return 1;
    }
}
