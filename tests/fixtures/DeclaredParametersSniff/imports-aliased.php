<?php

declare(strict_types=1);

namespace Acme\AliasedImports;

// An alias binds its *local* name. Both imports below are unqualified — they
// name functions in the global namespace — but neither binds its local name to
// PHP's function *of that name*, so calls through them are not dynamic
// argument reads.
use function tally as func_num_args;
use function collect as func_get_args;

// A self-alias renames nothing, so it still binds PHP's own function and calls
// through it stay flagged.
use function func_get_arg as func_get_arg;

class Consumer
{
    public function counted(): int
    {
        return func_num_args();
    }

    public function collected(): array
    {
        return func_get_args();
    }

    public function selfAliased(): mixed
    {
        return func_get_arg(0);
    }
}
