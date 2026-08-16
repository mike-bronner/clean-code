<?php

declare(strict_types=1);

namespace App\Billing;
use App\Billing\Support\Loggable;
use stdClass;
use App\Models\Invoice;
use RuntimeException;
use Throwable;

class Ledger
{
    use Loggable;

    public function make(): stdClass
    {
        return new stdClass();
    }

    public function invoice(): Invoice
    {
        return new Invoice();
    }

    public function label(): string
    {
        return Invoice::label();
    }

    public function guard(): void
    {
        try {
            throw new RuntimeException('nope');
        } catch (Throwable $exception) {
            unset($exception);
        }
    }
}
