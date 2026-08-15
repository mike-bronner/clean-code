<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

const LIMIT = 10;

require __DIR__ . '/bootstrap.php';

$user = User::first();

if ($user === null) {
    $user = new User();
} else {
    $user->touch();
}

foreach ([$user] as $entry) {
    echo $entry->name;
}

while (count([]) > 0) {
    break;
}

do {
    $tries = 1;
} while ($tries < LIMIT);

switch ($user) {
    default:
        break;
}

try {
    $user->save();
} catch (Throwable) {
    echo 'failed';
} finally {
    echo 'done';
}

function present(User $user): string
{
    return $user->name;
}

present($user);

echo present($user);

return $user;

?>
<p>Rendered</p>
