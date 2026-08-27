<?php

/**
 * The same shape with a class constant named `TRAIT`, which is a declaration
 * that opens no body at all.
 *
 * One construct per file, each run on its own, so a fix that handles one
 * spelling of a member name and not the others is caught here rather than
 * carried by a sibling.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\Constant {
    class Holder
    {
        public const TRAIT = 'trait';

        public const INTERFACE = 'interface';
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
