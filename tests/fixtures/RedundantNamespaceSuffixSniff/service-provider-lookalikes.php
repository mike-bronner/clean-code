<?php

declare(strict_types=1);

namespace App\Providers {
    // Violation: the suffix is Provider, not ServiceProvider.
    class PaymentProvider
    {
    }

    // Violation: the name is the bare suffix, with no prefix before it.
    class ServiceProvider
    {
    }

    // Violation: the exemption covers a class, not an interface.
    interface ContractServiceProvider
    {
    }
}

// Violation: a sub-namespace of App\Providers, not App\Providers itself.
namespace App\Providers\Billing {
    class BillingServiceProvider
    {
    }
}

// Violation: an ordinary redundant suffix, outside App\Providers.
namespace App\Services {
    class BillingService
    {
    }
}
