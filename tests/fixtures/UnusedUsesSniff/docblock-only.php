<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\PaymentFailedException;
use App\Models\Invoice;

class InvoiceProcessor
{
    /**
     * @param Invoice $invoice
     * @throws PaymentFailedException
     */
    public function process($invoice): void
    {
        $invoice->settle();
    }
}
