<?php

declare(strict_types=1);

namespace App\Billing;

class Reporter
{
    public function staticCall(): string
    {
        return \App\Models\Invoice::label();
    }

    public function partiallyQualified(): Models\Invoice
    {
        return new Models\Invoice();
    }
}

$reporter = new \stdClass();
