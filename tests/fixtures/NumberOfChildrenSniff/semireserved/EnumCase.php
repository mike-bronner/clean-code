<?php

/**
 * The same shape with an enum case named `Trait`, which ends in a semicolon and
 * opens no body either.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\EnumCase {
    enum Kind
    {
        case Trait;

        case Interface;
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
