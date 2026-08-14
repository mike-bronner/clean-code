<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The other half of the root-qualified rule. In a file declaring no namespace,
 * `\Money` and `Money` are one class and the sniff reads them as one; here they
 * are two, and it must not.
 *
 * Both sides of the detection carry that:
 *
 *   - `fromRoot(): \Money` returns the *global* `Money`, not this one, so it is
 *     not a named constructor of this class and is never inspected — silent
 *     however it builds its instance.
 *   - `fromGlobal()` is a named constructor of this class, and `new \Money()`
 *     builds the global one, so it reaches no constructor of its own and is
 *     reported.
 *
 * Reading a leading separator as this class regardless of the namespace flips
 * both: the first would be reported and the second would fall silent.
 * `fromCents()` and `fromSerialized()` hold the unqualified behaviour steady
 * beside them — a namespace changes nothing about a bare `self`.
 */

final class Money
{
    public function __construct(private readonly int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromSerialized(string $payload): self
    {
        return unserialize($payload);
    }

    public static function fromGlobal(): self
    {
        return new \Money();
    }

    public static function fromRoot(): \Money
    {
        return unserialize('');
    }
}
