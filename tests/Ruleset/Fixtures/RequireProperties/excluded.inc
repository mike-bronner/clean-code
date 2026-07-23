<?php

declare(strict_types=1);

namespace App\Fixtures;

// Interfaces, traits, and enums are not classes: they cannot encapsulate
// instance state the way a class does, so none is ever flagged even with zero
// property declarations.
interface Payable
{
    public function pay(): void;
}

trait Timestamps
{
    public function touch(): void
    {
    }
}

enum Suit
{
    case Hearts;
    case Spades;
}
