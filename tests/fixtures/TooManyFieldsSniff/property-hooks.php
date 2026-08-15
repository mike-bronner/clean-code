<?php

declare(strict_types=1);

namespace App\Fixtures;

// PHP 8.4 property hooks. PHP_CodeSniffer opens no scope for a hook body, so
// every variable written inside one reaches the counter looking class-scoped —
// this class holds 3 fields and 17 such variables. It must stay silent at the
// default threshold, and tests/Standards/TooManyFieldsTest.php pins the count
// at exactly 3 by lowering the threshold.
//
// There is no PHPMD verdict to compare against: PHPMD 2.15.0 cannot read this
// file at all, failing with "Unexpected token: {" at the first hook.
class Temperature
{
    private float $celsius = 0.0;

    public float $fahrenheit {
        get => ($this->celsius * 1.8) + 32.0;
        set (float $value) {
            $this->celsius = ($value - 32.0) / 1.8;
        }
    }

    public string $label {
        get {
            $degrees = $this->celsius;
            $rounded = round($degrees);
            $sign = $rounded < 0 ? '-' : '';
            $digits = abs($rounded);
            $unit = 'C';
            $separator = ' ';
            $prefix = 'about';
            $suffix = '.';
            $parts = [$prefix, $sign, $digits, $separator, $unit];
            $joined = implode('', $parts);
            $trimmed = trim($joined);
            $upper = strtoupper($trimmed);
            $lower = strtolower($upper);

            return $lower . $suffix;
        }
    }
}
