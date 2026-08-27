<?php

/**
 * The same shape with a trait adaptation that aliases a method to `trait`.
 *
 * The keyword sits inside a class body here rather than at the top level of one,
 * which is a different position again — and the entry it leaves behind still
 * outlives the class that holds it, because nothing on the way out clears it.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\Alias {
    trait Marks
    {
        public function mark(): void
        {
        }
    }

    class Holder
    {
        use Marks {
            mark as trait;
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
