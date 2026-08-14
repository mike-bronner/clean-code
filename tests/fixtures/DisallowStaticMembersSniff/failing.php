<?php

declare(strict_types=1);

namespace App\Fixtures;

class Registry
{
    public static int $instances = 0;

    public static function create(): self
    {
        return new self();
    }
}

class Counter
{
    private int $count = 0;

    public static function reset(): void
    {
    }

    protected static ?string $label = null;
}
