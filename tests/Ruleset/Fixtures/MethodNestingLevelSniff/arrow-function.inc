<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelArrowFunction
{
    // An arrow function is an anonymous function and counts as a nesting level
    // exactly like a closure. Nested two deep, the `fn` sits at level 3 and is
    // reported at its own position; its inline `match` body is also at level 3.
    public function overNestedArrowFunction(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $fn = fn () =>
                    match ($item) {
                        default => 0,
                    };
            }
        }
    }
}
