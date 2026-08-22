<?php

declare(strict_types=1);

namespace App\Services {
    class BillingService
    {
    }
}

namespace App\Policies {
    class UserPolicy
    {
    }
}

namespace App\Statuses {
    class OrderStatus
    {
    }
}

namespace App\Cases {
    class UseCase
    {
    }
}

namespace App\Analyses {
    class RevenueAnalysis
    {
    }
}

namespace App\Http\Controllers\Api {
    class TokenController
    {
    }
}

namespace App\Livewire\Forms {
    class LoginForm
    {
    }
}

namespace App\Http\Controllers {
    class Controller
    {
    }
}

namespace App\Payments {
    interface RefundablePayment
    {
    }

    trait RecordsPayment
    {
    }

    enum CapturedPayment: string
    {
        case Card = 'card';
    }
}

namespace App\Notifications {
    class OrderNOTIFICATION
    {
    }
}

namespace App\WEBHOOKS {
    class StripeWebhook
    {
    }
}

namespace App\Services\Service {
    class BillingService
    {
    }
}

namespace App\Movies {
    class Movie
    {
    }
}
