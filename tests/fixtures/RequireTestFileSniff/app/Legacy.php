<?php

declare(strict_types=1);

namespace Fixture\App;

/**
 * A concrete, untested class under `app/` — the second shipped source-directory
 * name. Drop it from $sourceDirectories and this file has no source root at
 * all, so the class falls silent instead of reporting.
 */
class Legacy
{
    public function value(): int
    {
        return 7;
    }
}
