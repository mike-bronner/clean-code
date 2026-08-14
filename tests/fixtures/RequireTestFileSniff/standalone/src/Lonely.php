<?php

declare(strict_types=1);

namespace Fixture\Standalone\Src;

/**
 * A concrete class in a project with no test directory at all — standalone/
 * deliberately holds nothing but src/. This is the case the rule most exists to
 * catch, and it is also the one where the expected pattern names a directory
 * that is not there, so the lookup has to answer "no companion" rather than
 * error out.
 */
class Lonely
{
    public function value(): int
    {
        return 11;
    }
}
