<?php

declare(strict_types=1);

namespace App\Models;

// The checkout itself sits inside a directory called `app` (a common container
// layout, /app/app/Models/User.php). The *last* app segment is the application
// folder, so this resolves to app/Models — a no-suffix folder — rather than
// treating "app" as the base folder and demanding an "app" suffix.
class User
{
}
