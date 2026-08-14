<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A concrete class with no companion test anywhere. The plain violation.
 */
class Untested
{
    public function value(): int
    {
        return 2;
    }
}
