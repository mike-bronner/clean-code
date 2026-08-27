<?php

/**
 * The `Holder::class` spelling, which is the one member-name position this parse
 * always read correctly — by naming it, and only it, in a list of the ways
 * `class` can be spelled without declaring anything.
 *
 * That list is gone, replaced by a test of what a declaration looks like, so
 * this file is here to say the case it held is still held. It is the boundary
 * of the same shape rather than a new one: `Holder::class` and `Holder::TRAIT`
 * differ only in which keyword the tokenizer hands back.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\ClassConstant {
    class Holder
    {
    }

    class Reader
    {
        public function read(): string
        {
            return Holder::class;
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
