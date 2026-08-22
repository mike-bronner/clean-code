<?php

declare(strict_types=1);

namespace Fixture;

/**
 * The contract floor's violating fixture: concrete classes with no companion
 * test resolvable from this directory. `final` and `readonly` are here beside
 * the plain declaration because neither modifier exempts a class — only
 * `abstract` does, and it is easy to widen that check to any modifier at all.
 */
class PlainService
{
    public function value(): int
    {
        return 1;
    }
}

final class FinalService
{
    public function value(): int
    {
        return 2;
    }
}

readonly class ReadonlyService
{
    public function value(): int
    {
        return 3;
    }
}
