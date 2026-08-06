<?php

declare(strict_types=1);

/**
 * Every assignment-in-condition shape PHPMD's IfStatementAssignment rule
 * reports. Verified against phpmd 2.15 with rulesets/cleancode.xml: each line
 * below is flagged by both tools at the same line.
 */
class ViolatingConditions
{
    public function evaluate(array $data, string $name): string
    {
        if ($foo = 'bar') {
            return 'a';
        } elseif ($baz = 0) {
            return 'b';
        }

        if ($first = 1 && $second = 2) {
            return 'c';
        }

        if (strlen($nested = 'x') > 0) {
            return 'd';
        }

        if ($property = $this->name = 'chained') {
            return 'e';
        }

        if ($element['key'] = 1) {
            return 'f';
        }

        if (!$negated = getenv('X')) {
            return 'g';
        }

        if (($wrapped = getenv('Y')) !== false) {
            return 'h';
        }

        if ($outer = 1) {
            if ($inner = 2) {
                return 'i';
            }
        }

        return $foo . $baz . $first . $second . $nested . $element . $negated . $wrapped . $outer . $inner;
    }

    private string $name = '';
}
