<?php

declare(strict_types=1);

namespace Acme\MixedGroupImports;

// A mixed group prefixes the individual entry rather than the whole statement.
// The `function` entry below is a qualified import, so it binds a different
// symbol and calls through it are not dynamic argument reads. The class and
// const entries beside it live in their own symbol tables.
use Acme\Support\{Collector, function func_get_args, const MODE};

// The prefix belongs to its own entry only: the unprefixed entry here is a
// *class* import, which never affects how a function name resolves.
use Acme\Support\{function normalize, func_num_args};

// PHP 8 allows a reserved word as a name segment, so `function` is not always
// a prefix. Here it names part of the namespace imported *from*, and the entry
// is a class import — `func_get_arg` stays PHP's own function.
use Acme\{function\func_get_arg, function tally};

class Consumer
{
    public function imported(): array
    {
        return func_get_args();
    }

    public function classEntryBindsNoFunction(): int
    {
        return func_num_args();
    }

    public function segmentNamedFunction(): mixed
    {
        return func_get_arg(0);
    }

    public function collaborators(): string
    {
        return normalize(Collector::class) . tally() . MODE;
    }
}
