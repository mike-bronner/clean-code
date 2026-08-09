<?php

/**
 * Small declarations for the property-configuration tests, which lower
 * `minimum` far below PHPMD's default so a handful of lines is enough to
 * cross it. Cross-checked against live PHPMD 2.15.0 runs at the same
 * thresholds; see docs/phpmd/codesize-excessivemethodlength.md.
 */

class ConfiguredThresholds
{

    public function holdsAClosure(): void
    {
        $callback = function (): int {
            $first = 1;

            // A comment line inside the nested closure.
            $second = 2;
            $third = 3;
            $fourth = 4;

            return $first + $second + $third + $fourth;
        };

        $callback();
    }

    public function short(): void
    {
        $only = 1;
    }

    public function withMultilineString(): string
    {
        $text = 'first
second
third
fourth';

        return $text;
    }
}

function configuredStandalone(): void
{
    $value1 = 1;
    $value2 = 2;
    $value3 = 3;
    $value4 = 4;
    $value5 = 5;
    $value6 = 6;
    $value7 = 7;
    $value8 = 8;
    $value9 = 9;
    $value10 = 10;
    $value11 = 11;
}
