<?php

declare(strict_types=1);

/**
 * A brace-less chain whose last clause is cut off before its body, so the body
 * walk starts on the `}` that closes the function around it.
 *
 * The two clauses before the cut share one discriminator read and compare it
 * against scalar literals, and an `else` is written after the closing brace, so
 * a walk that read on past that brace would take it as this chain's third
 * branch and report a file PHP rejects.
 */

function truncatedBlock(object $shape): string
{
    if ($shape->type === 'circle')
        return 'Circle';
    elseif ($shape->type === 'square')
}
else {
    return 'Unknown';
}
