<?php

declare(strict_types=1);

namespace App\Fixtures;

// A static method is the same anti-pattern in every object-oriented container.
// The abstract method below also carries a `static` return type on the same
// line — only the modifier is flagged, never the return type.
interface Factory
{
    public static function make(): self;
}

abstract class Base
{
    abstract public static function build(): static;
}

trait Shareable
{
    public static function share(): void
    {
    }
}

enum Suit: string
{
    case Hearts = 'H';

    public static function default(): self
    {
        return self::Hearts;
    }
}
