<?php

declare(strict_types=1);

namespace App;

use App\Services\PaymentGateway;
use App\Support\Clock;

class CheckoutService
{
    public function __construct(private PaymentGateway $gateway, private Clock $clock)
    {
    }

    public function checkout(): void
    {
        $this->gateway->charge($this->clock->now());
    }
}
