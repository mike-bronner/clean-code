<?php

declare(strict_types=1);

namespace App\Fixtures;

// PHPMD's threshold is inclusive: PHPMD\Rule\Design\LongClass returns early
// only when the length is *below* `minimum`, so a class of exactly `minimum`
// lines is already a violation. These three classes sit one under, exactly on,
// and one over the threshold of 10 the behaviour test configures.

class OneUnderThreshold
{
    // Filler 1.
    // Filler 2.
    // Filler 3.
    // Filler 4.
    // Filler 5.
    // Filler 6.
}

class AtThreshold
{
    // Filler 1.
    // Filler 2.
    // Filler 3.
    // Filler 4.
    // Filler 5.
    // Filler 6.
    // Filler 7.
}

class OneOverThreshold
{
    // Filler 1.
    // Filler 2.
    // Filler 3.
    // Filler 4.
    // Filler 5.
    // Filler 6.
    // Filler 7.
    // Filler 8.
}
