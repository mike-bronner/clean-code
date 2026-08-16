<?php

declare(strict_types=1);

namespace App\Fixtures;

/**
 * Only the guarded pairs, nothing else, so an assertion of silence over this
 * file is an assertion about the guard rather than about an empty fixture.
 *
 * A same-direction sign pair is left spaced on purpose: `- -$number` closed up
 * becomes `--$number`, a pre-decrement, which is different code. The guard
 * withholds the report as well as the fix, so removing it turns this file into
 * two violations and reddens every assertion that reads it.
 */
function guardedSignPairs(int $number): void
{
    $doubleNegation = - -$number;
    $doubleIdentity = + +$number;
}
