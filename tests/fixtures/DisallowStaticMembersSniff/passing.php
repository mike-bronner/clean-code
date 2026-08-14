<?php

declare(strict_types=1);

namespace App\Fixtures;

// Every `static` below is a non-declaration construct the sniff must leave
// alone: a class constant, a `static` return type, late static binding, a
// function-local static, and a static arrow function. None declares a static
// member, so this file must produce zero violations.
class Invoice
{
    public const STATUS_PAID = 'paid';

    private int $total = 0;

    public function total(): int
    {
        static $calls = 0;
        $calls++;

        return $this->total;
    }

    public function copy(): static
    {
        return new static();
    }

    public function formatter(): callable
    {
        return static fn (int $amount): string => (string) $amount;
    }
}
