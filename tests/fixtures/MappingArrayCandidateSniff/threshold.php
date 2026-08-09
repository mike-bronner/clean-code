<?php

declare(strict_types=1);

/**
 * Threshold fixture for CleanCode.Conditionals.MappingArrayCandidate.
 *
 * One chain, deliberately two branches long — one below the default minimum.
 * The sniff must be silent on it as shipped, and must report it once the
 * $minimumBranches property is lowered to 2. Nothing else about the chain
 * changes between the two runs, so the property is the only thing either
 * assertion can be reading.
 */

function twoBranches(string $code): string
{
    if ($code === 'a') {
        return 'Alpha';
    } else {
        return 'Unknown';
    }
}
