<?php

declare(strict_types=1);

// Not a violation: Laravel names a provider *ServiceProvider in App\Providers,
// and bootstrap/providers.php registers it under that name.
namespace App\Providers {
    class AppServiceProvider
    {
    }

    class AuthServiceProvider
    {
    }

    class NovaServiceProvider
    {
    }
}

// Not a violation: the namespace comparison ignores case, like the App root.
namespace app\providers {
    class EventServiceProvider
    {
    }
}
