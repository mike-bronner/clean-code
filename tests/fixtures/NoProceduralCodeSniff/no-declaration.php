<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

$user = User::first();

if ($user !== null) {
    echo $user->name;
}
