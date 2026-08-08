<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Support\Loggable;
use App\Billing\Support\Money;
use App\Models\Invoice;
use RuntimeException;
use Throwable;

class Ledger extends Invoice
{
    use Loggable;

    public function record(Money $amount): self
    {
        assert($amount instanceof Money);

        self::reopen();

        return static::reopen();
    }

    public function total(): Money
    {
        return Money::zero();
    }

    public function guard(): void
    {
        try {
            throw new RuntimeException('nope');
        } catch (Throwable $exception) {
            unset($exception);
        }
    }

    public function describe(string $value): string
    {
        return \strlen($value) . strlen($value) . \PHP_EOL . PHP_EOL;
    }
}
