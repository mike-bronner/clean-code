<?php

namespace App\Http\Controllers {
    use App\Models\User;
    use Vendor\Account;

    User::create(['name' => $name]);
}

namespace App\Jobs {
    User::create(['name' => $name]);
}

namespace App\Models {
    Account::create(['name' => $name]);
}

namespace App\Console {
    use App\Models\Invoice;

    Invoice::create(['total' => $total]);
    User::create(['name' => $name]);
}

namespace App\Support {
    Invoice::create(['total' => $total]);
}
