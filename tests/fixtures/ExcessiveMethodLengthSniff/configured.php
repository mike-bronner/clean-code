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

/**
 * A declaration whose modifiers are split over several lines with a comment
 * written between two of them. PDepend starts the node at the first modifier
 * whatever follows it, so the span is measured from `public` on line 70 and not
 * from `static` on line 72. Live PHPMD 2.15.0 reports this declaration at line
 * 70 and measures it at 7 lines, 4 of them executable.
 */
class CommentBetweenModifiers
{

    public
    /* Static because the callers hold no instance of this class. */
    static function anchorsAtTheFirstModifier(): void
    {
        $first = 1;
        $second = 2;
    }
}
