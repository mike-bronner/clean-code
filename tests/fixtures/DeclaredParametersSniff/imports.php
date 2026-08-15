<?php

declare(strict_types=1);

namespace Acme\Imports;

// A qualified import binds a different symbol, so an unqualified call resolves
// to the import and never to PHP's function. Every entry of a group use
// carries the group's prefix, so both names below are qualified.
use function Acme\Support\normalize;
use function Acme\Support\{func_get_args, func_num_args};

// An *unqualified* import under the same name still names PHP's own function,
// so calls through it remain dynamic argument reads.
use function func_get_arg;

// A class import lives in a separate symbol table and does not affect function
// resolution, even when it is aliased onto a function's name.
use Acme\Support\Collector as func_get_arg;

class Consumer
{
    public function imported(): array
    {
        return func_get_args();
    }

    public function grouped(): int
    {
        return func_num_args();
    }

    public function unqualifiedImport(): mixed
    {
        return func_get_arg(0);
    }

    public function qualifiedIsStillGlobal(): array
    {
        // A leading separator bypasses the imports above and names PHP's own
        // function.
        return \func_get_args();
    }
}
