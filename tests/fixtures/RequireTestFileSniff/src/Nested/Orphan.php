<?php

declare(strict_types=1);

namespace Fixture\Src\Nested;

/**
 * A concrete class below the source root whose only same-named test sits at the
 * *wrong* level, tests/OrphanTest.php rather than tests/Nested/OrphanTest.php.
 * The source-relative directory is part of the expected path, so this is a
 * violation — and it is the shape that proves it: ignore `{path}` and the stray
 * test satisfies the lookup and this class falls silent.
 */
class Orphan
{
    public function value(): int
    {
        return 4;
    }
}
