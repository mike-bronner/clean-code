<?php

declare(strict_types=1);

namespace App\Fixtures;

// A file-level import, not a trait use: it sits outside every class body, so
// it must not make the classes below look like they compose state.
use RuntimeException;

interface Formats
{
    public function format(string $input): string;
}

// A methods-only class: behaviour with nothing to hold.
class Formatter
{
    public function format(string $input): string
    {
        return trim($input);
    }
}

// An empty stub. No methods either, so nothing at all is encapsulated.
class Marker
{
}

// `implements` is not `extends`: an interface declares no instance state, so
// there is nothing to inherit and the class is still empty of data.
class UpperFormatter implements Formats
{
    public function format(string $input): string
    {
        return strtoupper($input);
    }
}

// Plain constructor parameters are call arguments, not stored state — only a
// visibility modifier would promote them to properties.
class Mailer
{
    public function send(string $to, string $subject): bool
    {
        return $to !== '' && $subject !== '';
    }
}

// Class constants are not the target of this standard.
class HttpStatus
{
    public const OK = 200;

    public const NOT_FOUND = 404;
}

// An abstract class is a class declaration; only interfaces and traits are
// excluded, so a property-less one is reported like any other.
abstract class Transformer
{
    abstract public function transform(string $input): string;
}

// Local variables inside a method body belong to that method, not the class.
class Summer
{
    public function sum(array $numbers): int
    {
        $total = 0;

        foreach ($numbers as $number) {
            $total += $number;
        }

        return $total;
    }
}

// A closure's `use (...)` binding is not a trait import. It lives inside a
// method body, so it belongs to the closure rather than to Runner.
class Runner
{
    public function run(int $limit): callable
    {
        return function () use ($limit): int {
            return $limit * 2;
        };
    }
}

// State that belongs to a *nested* anonymous class is not this class's state.
// The anonymous class itself is not reported — the sniff registers on T_CLASS
// only — so Builder's own declaration line is the sole violation here.
class Builder
{
    public function build(): object
    {
        return new class () {
            public int $value = 0;

            public function throwIt(): void
            {
                throw new RuntimeException('nope');
            }
        };
    }
}
