<?php

declare(strict_types=1);

namespace App\Fixtures;

function guarded(int $a): void
{
    // A unary sign acting on another sign: closing the gap would fuse the pair
    // into a decrement/increment (`--$a`, `++$a`) and change the meaning, so
    // the spacing is deliberately left untouched — no violation, no fix.
    $doubleNegation = - -$a;
    $doublePlus = + +$a;
}
