<?php

declare(strict_types=1);

namespace App\Sample;

use DateTimeImmutable;
use RuntimeException;

class Widget
{
    public function guard(bool $ok): void
    {
        if (! $ok) {
            throw new RuntimeException('Widget is not ok.');
        }
    }
}
