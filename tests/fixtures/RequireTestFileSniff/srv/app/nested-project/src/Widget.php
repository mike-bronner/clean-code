<?php

declare(strict_types=1);

namespace Fixture\Nested\Src;

/**
 * A project checked out below a directory that is itself called `app`. The
 * source root is the `src` closest to the file, so the project root is
 * nested-project/ and the companion is found at
 * nested-project/tests/WidgetTest.php.
 *
 * Anchor on the *first* source segment instead and the project root becomes
 * srv/, the lookup goes to srv/tests/WidgetTest.php, and this compliant class
 * reports.
 */
class Widget
{
    public function value(): int
    {
        return 8;
    }
}
