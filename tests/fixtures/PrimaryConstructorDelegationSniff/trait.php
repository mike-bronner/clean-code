<?php

declare(strict_types=1);

/**
 * Trait-declared named constructors, and the one shape the sniff cannot see.
 *
 * A trait's static methods are inspected like a class's, which is what
 * `T_TRAIT` earns its place in the constructible scopes for: `new self(...)`
 * and `new static(...)` written in a trait reach the consuming class's primary
 * constructor at use-time, so the same delegation question applies, and a
 * trait body that bypasses it is the same defect.
 *
 * The blind spot is the return type. A trait is compiled into whichever class
 * uses it, and one file never says which class that is, so a trait method
 * return-typed to its eventual consumer by name — `: Money` inside
 * `trait Zeroable` — matches neither `self`/`static` nor the trait's own name,
 * and is never recognized as a named constructor however its body builds the
 * instance. `fromSerialized()` below is that shape and stays silent, the one
 * entry here the sniff passes over rather than clears. Nothing available in a
 * single file resolves it.
 */

trait Zeroable
{
    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromCents(int $cents): static
    {
        return new static($cents);
    }

    public static function fromString(string $value): self
    {
        return self::fromCents((int) $value);
    }

    public static function fromReflection(): self
    {
        $reflection = new ReflectionClass(__CLASS__);

        return $reflection->newInstanceWithoutConstructor();
    }

    public static function fromSerialized(string $payload): Money
    {
        return unserialize($payload);
    }
}
