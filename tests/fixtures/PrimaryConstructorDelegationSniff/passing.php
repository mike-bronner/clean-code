<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Constructors.PrimaryConstructorDelegation.
 *
 * Two things keep this file discriminating:
 *
 *   1. Every accepted delegation form: `new self(...)`, `new static(...)`,
 *      `new <DeclaringClass>(...)`, its root-qualified spelling, a call to
 *      another named constructor, and a call to a plain static helper that is
 *      not itself a named constructor — the breadth #184 settled on. The
 *      own-name and root-qualified spellings appear on the `::` side too, so
 *      the segment check that rejects `Money\Amount` cannot pass by rejecting
 *      every own-name match that has a token after it. Nullable, union and
 *      root-qualified return types are included, because those are the shapes
 *      a `tryFrom()` and a fully-written type carry.
 *   2. Every near-miss the sniff must stay silent on: an instance-level wither
 *      returning `self`, a static method returning anything else, an abstract
 *      declaration, an interface signature, an enum's named constructor (which
 *      cannot use `new` at all), a plain function at file scope returning
 *      `self`, and a named constructor whose delegation sits inside a closure
 *      or an arrow function, where the class binding still holds.
 *
 * Which of these redden this file on their own, and which are boundaries
 * documented here but pinned from failing.php, is set out beside the
 * compliant-fixture test in tests/Standards/PrimaryConstructorDelegationTest.php.
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

    public static function fromDollars(int $dollars): static
    {
        return new static($dollars * 100);
    }

    public static function zero(): Money
    {
        return new Money(0);
    }

    public static function fromString(string $value): self
    {
        return self::fromCents((int) $value);
    }

    public static function tryFromString(string $value): ?self
    {
        return static::hydrate($value);
    }

    public static function fromMixed(string $value): self|null
    {
        return self::hydrate($value);
    }

    public static function fromRoot(int $cents): \Money
    {
        return new self($cents);
    }

    public static function fromRootInstance(int $cents): self
    {
        return new \Money($cents);
    }

    public static function fromOwnNameFactory(int $cents): self
    {
        return Money::fromCents($cents);
    }

    public static function fromRootNameFactory(int $cents): self
    {
        return \Money::fromCents($cents);
    }

    /**
     * Not a named constructor itself — its return type names neither the class
     * nor `self`/`static` — so the sniff never inspects it, and the methods
     * above still route through the primary constructor one hop further out.
     */
    private static function hydrate(string $value): object
    {
        return new self((int) $value);
    }

    public function withCents(int $cents): self
    {
        $clone = clone $this;

        return $clone;
    }

    public static function currency(): string
    {
        return 'USD';
    }

    public function cents(): int
    {
        return $this->cents;
    }
}

final class DeferredMoney
{
    public function __construct(private readonly int $cents)
    {
    }

    public static function lazy(int $cents): self
    {
        $make = static fn (): self => new self($cents);

        return $make();
    }

    public static function mapped(array $rows): self
    {
        $built = array_map(static function (int $row): self {
            return new self($row);
        }, $rows);

        return $built[0];
    }
}

abstract class Temperature
{
    abstract public static function fromCelsius(float $degrees): static;
}

interface Buildable
{
    public static function build(array $attributes): self;
}

enum Status: string
{
    case Active = 'active';
    case Retired = 'retired';

    public static function fromLabel(string $label): self
    {
        return self::Active;
    }
}

function make(): self
{
    return unserialize('');
}

/**
 * The anonymous-class counterpart: no class name to match, so `new self(...)`
 * is what routes to its primary constructor, and the sniff accepts it without
 * ever comparing against a name.
 */
$registry = new class () {
    public function __construct(private readonly array $entries = [])
    {
    }

    public static function empty(): self
    {
        return new self();
    }
};
