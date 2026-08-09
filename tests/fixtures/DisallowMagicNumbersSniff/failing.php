<?php

declare(strict_types=1);

const RETRY_LIMIT = 3;

$attempts = RETRY_LIMIT * 12;

function schedule(int $count): int
{
    $capacity = $count * 7;

    if ($capacity > 42) {
        $capacity = min($capacity, 500);
    }

    return $capacity + 99;
}
