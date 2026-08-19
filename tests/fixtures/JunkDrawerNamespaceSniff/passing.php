<?php

declare(strict_types=1);

namespace App\Billing {
    use App\Helpers\Formatter;

    class Helpers
    {
        public function format(Formatter $formatter): string
        {
            return namespace\Helpers\present($formatter);
        }
    }
}

namespace App\Http\Controllers {
    class InvoiceController
    {
    }
}

namespace App\Models {
}

namespace App\Providers {
}

namespace App\HelperRegistry {
}

namespace App\Uncommon {
}

namespace App\Generals {
}

namespace {
}
