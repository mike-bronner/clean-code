<?php

declare(strict_types=1);

namespace Vendor\Package;

use RuntimeException;

final class CompliantExample
{
    public const VERSION = '1.0';

    private int $count = 0;

    public function __construct(private readonly string $name)
    {
    }

    public function increment(int $amount): int
    {
        if ($amount < 0) {
            throw new RuntimeException('Amount must not be negative for ' . $this->name . '.');
        }

        foreach ([1, 2, 3] as $step) {
            $this->count += $step * $amount;
        }

        return $this->count;
    }
}
