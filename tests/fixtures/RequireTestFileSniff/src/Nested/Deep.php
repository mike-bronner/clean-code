<?php

declare(strict_types=1);

namespace Fixture\Src\Nested;

/**
 * A concrete class below the source root, mirrored at tests/Nested/DeepTest.php.
 * Drop `{path}` from the resolved pattern and this still passes against
 * tests/DeepTest.php — which is why Orphan.php sits beside it.
 */
class Deep
{
    public function value(): int
    {
        return 3;
    }
}
