<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A trait under the source root with no test. T_TRAIT, so never flagged.
 */
trait Helper
{
    public function value(): int
    {
        return 5;
    }
}
