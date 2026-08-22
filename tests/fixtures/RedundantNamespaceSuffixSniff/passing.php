<?php

declare(strict_types=1);

namespace App\Services {
    class Billing
    {
    }
}

namespace App\Services\Billing {
    class BillingGateway
    {
    }
}

namespace App\Ads {
    class Squad
    {
    }
}

namespace App\Notifications {
    class Ordernotification
    {
    }
}

namespace App\Http\Controllers {
    class UserProfile
    {
    }
}

namespace App {
    class Application
    {
    }
}

// The `Tests\` half of the tooling exemption is fixture-only, and has to be:
// outside fixtures no file in this repository declares a bare `Tests\`-rooted
// namespace, because this package's own tests are `MikeBronner\CleanCode\Tests\…`.
// The `Sniffs\` half is different — RedundantNamespaceSuffixTest sweeps the
// sniff across the package's real source for it. What carries this half is that
// the sniff's root gate has no `Tests\`-specific branch: both roots leave
// through the same one test, so that sweep exercises the logic under this line
// too.
namespace Tests\Services {
    class PaymentService
    {
    }
}

namespace Vendor\App\Repositories {
    class UserRepository
    {
    }
}

namespace App\Factories {
    class Anonymous
    {
        public function make(): object
        {
            return new class {
            };
        }
    }
}

namespace {
    class GlobalService
    {
    }
}
