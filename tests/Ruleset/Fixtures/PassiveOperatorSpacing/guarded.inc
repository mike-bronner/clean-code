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

    // Error control before a bare `+`/`-` sign: stripping the space would leave
    // `@-$a`, which the wired-in PSR12.Operators.OperatorSpacing re-reads as a
    // binary operator and re-spaces — so it too is ceded to PSR12 and left
    // untouched (no violation, no fix) to avoid a phpcbf oscillation.
    $suppressedNegation = @ - $a;
    $suppressedIdentity = @ + $a;
}
