<?php

/**
 * The two exclusion lists, each with a comment the shipped defaults silence and
 * a control the defaults always report. Retuning one list must flip only the
 * comment it owns.
 */

declare(strict_types=1);

namespace Section\Configured;

class Retunable
{
    public function debtMarker(array $payload): array
    {
        // TODO: extract the normalisation below.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function formatterDirective(array $payload): array
    {
        // @formatter:off
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function noMarkerAtAll(array $payload): array
    {
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function formatterDirectiveWithTrailingText(array $payload): array
    {
        // @formatter:off for the block below
        $payload = array_change_key_case($payload);

        return $payload;
    }
}
