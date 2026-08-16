<?php

declare(strict_types=1);

/**
 * Shapes that resemble delegation without being it. Every named constructor
 * here is still reported, so a sniff that loosened any one condition would
 * fall silent on this file.
 *
 * `Ticket` collects the ones that mention the declaring class:
 *
 *   - `self::class` — a constant fetch, not a call, which is why a `(` has to
 *     follow the callee.
 *   - `parent::open()` and `new parent()` — the superclass's constructor is a
 *     different one.
 *   - `new \Other\Ticket()` and `\Other\Ticket::open()` — a name carrying a
 *     namespace segment, on both sides of the detection, which the sniff
 *     cannot resolve to this class. The leading separator alone is not the
 *     disqualifier: a root-qualified `\Ticket` here would be this class.
 *   - `new Ticket\Sub()` and `new \Ticket\Sub()` — the same defect with this
 *     class's name in the *head* position instead of the tail. `Ticket\Sub` is
 *     a class under a namespace that happens to be spelled like this class,
 *     not this class, so matching the first segment and stopping there would
 *     read both as delegation.
 *   - `Ticket\Sub::open()` and `\Ticket\Sub::open()` — the `::` counterpart,
 *     already out of reach of that defect because the token before `::` is the
 *     name's tail (`Sub`), which matches nothing here. Held so a rewrite that
 *     resolved a qualified name from its head would have to keep them silent.
 *   - `self::open()` inside `open()` — recursion with no `new` in it never
 *     reaches a constructor.
 *   - `Ticket::REGISTRY` — a class constant, again not a call.
 *
 * `Draft` covers the scope carve-out: `new self()` inside an anonymous class
 * builds *that* class, so the enclosing named constructor still bypasses its
 * own primary constructor.
 */

class Ticket extends BaseTicket
{
    public const REGISTRY = 'tickets';

    public function __construct(private readonly string $reference)
    {
    }

    public static function fromClassName(string $reference): self
    {
        return unserialize(self::class . $reference);
    }

    public static function fromParent(string $reference): self
    {
        return parent::open($reference);
    }

    public static function fromParentInstance(string $reference): self
    {
        return new parent($reference);
    }

    public static function fromNamespaced(string $reference): self
    {
        return new \Other\Ticket($reference);
    }

    public static function fromNamespacedFactory(string $reference): self
    {
        return \Other\Ticket::open($reference);
    }

    public static function fromSegmentHead(string $reference): self
    {
        return new Ticket\Sub($reference);
    }

    public static function fromRootSegmentHead(string $reference): self
    {
        return new \Ticket\Sub($reference);
    }

    public static function fromSegmentHeadFactory(string $reference): self
    {
        return Ticket\Sub::open($reference);
    }

    public static function fromRootSegmentHeadFactory(string $reference): self
    {
        return \Ticket\Sub::open($reference);
    }

    public static function open(string $reference): self
    {
        return self::open($reference);
    }

    public static function fromRegistry(string $reference): Ticket
    {
        return unserialize(Ticket::REGISTRY . $reference);
    }
}

class Draft
{
    public function __construct(private readonly string $body)
    {
    }

    public static function blank(): self
    {
        $factory = new class () {
            public function make(): object
            {
                return new self();
            }
        };

        return $factory->make();
    }
}
