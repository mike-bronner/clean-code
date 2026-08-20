<?php

/**
 * A debt marker is a word, not a substring. `Hack` in the first comment hands
 * that comment to Debt: Technical Debt; `Unshackle` in the second merely
 * contains the same four letters and is a section label like any other.
 */

declare(strict_types=1);

namespace Section\Markers;

class Substrings
{
    public function handle(array $payload): array
    {
        // Hack around the upstream casing defect.
        $payload = array_change_key_case($payload);

        // Unshackle the payload before merging.
        $payload['free'] = true;

        return $payload;
    }
}
