<?php

declare(strict_types=1);

// A short name is unique only within its namespace block, so a file with more
// than one block can declare two unrelated things that share it. Resolution
// has to stop at the block boundary: borrowing the other block's declaration
// would judge the wrong signature and name the wrong parameter in the fix.

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff\First {
    class Signal
    {
        public function __construct(?string $label = null)
        {
        }
    }

    function notify(?string $channel = null): void
    {
    }

    class Emitter
    {
        public function run(): void
        {
            // Both resolve inside this block, and both are optional.
            new Signal(null);
            notify(null);
        }
    }
}

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff\Second {
    class Signal
    {
        public function __construct(?string $tag)
        {
        }
    }

    function notify(?string $channel): void
    {
    }

    class Emitter
    {
        public function run(): void
        {
            // Same two short names, but these declarations take a *required*
            // parameter — nothing is being skipped, so neither is a violation.
            new Signal(null);
            notify(null);
        }
    }
}
