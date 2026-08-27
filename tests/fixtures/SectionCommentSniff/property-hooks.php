<?php

/**
 * A PHP 8.4 property hook. PHP_CodeSniffer opens no scope for a hook body, so
 * a comment written inside one reports the class as its innermost condition —
 * exactly as a comment between class members does — and the rule stays silent
 * on it. The ordinary method below carries the same comment shape and is
 * reported, so a sniff that had fallen silent on the whole file fails here.
 */

declare(strict_types=1);

namespace Section\Hooks;

class HookedProperty
{
    public int $doubled = 1 {
        get {
            // Double the stored value.
            $value = ($this->doubled * 2);

            return $value;
        }
    }

    public function ordinaryMethod(array $payload): array
    {
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }
}
