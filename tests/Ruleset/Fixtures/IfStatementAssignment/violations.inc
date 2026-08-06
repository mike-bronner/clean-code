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

        // Short-list destructuring. The Generic sniff's left-hand-side walk
        // accepts it because the target ends in "]", which is why the custom
        // list() sniff leaves this form alone — a claim that holds only as
        // long as the Generic sniff keeps reporting it, so it is pinned here
        // rather than assumed. PHPMD flags it too, so it belongs in this
        // fixture: verified against phpmd 2.15.
        if ([$shortFirst, $shortSecond] = $data) {
            return 'j';
        }

        return $foo . $baz . $first . $second . $nested . $element . $negated . $wrapped . $outer . $inner
            . $shortFirst . $shortSecond;
    }

    private string $name = '';
}
