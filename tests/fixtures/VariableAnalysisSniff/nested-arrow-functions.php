<?php

declare(strict_types=1);

namespace App;

class NestedArrowFunctions
{
    public function twoLevels(array $items): callable
    {
        return fn ($a) => array_map(fn ($b) => $a + $b, $items);
    }

    public function threeLevels(): callable
    {
        return fn ($a) => fn ($b) => fn ($c) => $a + $b + $c;
    }

    public function insideClosureWithUse(array $items, int $offset): callable
    {
        return function () use ($items, $offset): callable {
            return fn ($a) => array_map(fn ($b) => $a + $b + $offset, $items);
        };
    }

    public function readsMethodLocal(array $items): callable
    {
        $scale = 2;

        return fn ($a) => array_map(fn ($b) => ($a + $b) * $scale, $items);
    }

    public function insideString(array $items): callable
    {
        return fn ($a) => array_map(fn ($b) => "{$a}-{$b}", $items);
    }
}
