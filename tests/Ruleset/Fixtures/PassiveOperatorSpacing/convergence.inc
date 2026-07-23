<?php

declare(strict_types=1);

namespace App\Fixtures;

function convergence(int $a, int $b): int
{
    $incremented = $a++ + $b;
    $decremented = $a-- - $b;
    $identity = + $a;
    $negation = - $b;

    return $incremented + $decremented + $identity + $negation;
}
