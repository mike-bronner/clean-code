<?php

declare(strict_types=1);

namespace App\Billing;

class Ledger
{
    public function make(): \stdClass
    {
        return new \stdClass();
    }

    public function invoice(): \App\Models\Invoice
    {
        return new \App\Models\Invoice();
    }

    public function label(): string
    {
        return \App\Models\Invoice::label();
    }

    public function guard(): void
    {
        try {
            throw new \RuntimeException('nope');
        } catch (\Throwable $exception) {
            unset($exception);
        }
    }
}
