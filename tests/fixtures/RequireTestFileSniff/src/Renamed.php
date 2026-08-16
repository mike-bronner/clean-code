<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A file whose class is named something else. The companion is resolved from
 * the *file* name — tests/RenamedTest.php — because that is the mapping the
 * standard states (src/Foo/Bar.php -> a test named for Bar) and the one a
 * PSR-4 autoloader keys on. Resolve it from the class name instead and this
 * looks for DifferentNameTest.php and reports.
 *
 * The two disagreeing at all is a PSR-1/PSR-4 violation in its own right, and
 * belongs to the sniffs that own that rule rather than to this one.
 */
class DifferentName
{
    public function value(): int
    {
        return 9;
    }
}
