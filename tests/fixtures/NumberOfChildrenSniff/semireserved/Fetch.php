<?php

/**
 * The same shape with a class-constant fetch, `Holder::TRAIT`.
 *
 * `Type::class` was already read as declaring nothing, because it is the one
 * spelling of this shape that was known when the parse was written. The other
 * three keywords reach the same position by the same route: PHP emits T_TRAIT
 * for the name in `Holder::TRAIT` exactly as it emits T_CLASS for `Holder::class`.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\Fetch {
    class Holder
    {
        public const TRAIT = 'trait';
    }

    class Reader
    {
        public function read(): string
        {
            return Holder::TRAIT;
        }
    }
}

namespace Fixture\SemiReserved\Consumer {
    use Fixture\SemiReserved\Target\Imported;

    class Local
    {
    }

    class LocalChild extends Local
    {
    }

    class FirstImported extends Imported
    {
    }

    class SecondImported extends Imported
    {
    }
}

namespace Fixture\SemiReserved\Target {
    class Imported
    {
    }
}
