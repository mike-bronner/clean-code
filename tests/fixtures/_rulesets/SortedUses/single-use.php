<?php

declare(strict_types=1);

namespace App;

use App\Support\Clock;

class Stopwatch
{
    public function __construct(private Clock $clock)
    {
    }

    public function now(): string
    {
        return $this->clock->now();
    }
}
