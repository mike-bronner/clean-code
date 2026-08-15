<?php

declare(strict_types=1);

namespace App\Sample;

use DateTimeImmutable;
use RuntimeException;

class Order
{
    private string $unusedNote = 'never read anywhere';

    public function total(int $subtotal, int $unusedTax): int
    {
        // return $subtotal * 2;
        if ($subtotal < 0) {
            throw new RuntimeException('Subtotal must not be negative.');
        }

        return $subtotal;
    }

    private function unusedHelper(): void
    {
    }
}
