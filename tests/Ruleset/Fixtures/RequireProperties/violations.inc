<?php

declare(strict_types=1);

namespace App\Fixtures;

// A methods-only class has no state, and an empty stub class has none either —
// both are flagged at the class declaration keyword. The plain constructor and
// method parameters below are arguments, not stored properties.
class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

class EmptyStub
{
}

// A class whose only property lives in a nested anonymous class has no state of
// its own, so it is still flagged; the anonymous class is not a `class`
// declaration the sniff registers on.
class Shell
{
    public function make(): object
    {
        return new class {
            public int $value = 0;
        };
    }
}
