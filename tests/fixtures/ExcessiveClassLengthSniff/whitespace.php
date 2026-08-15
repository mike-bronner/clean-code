<?php

declare(strict_types=1);

namespace App\Fixtures;

// PHPMD's `ignore-whitespace` property does not merely drop blank lines: it
// swaps PDepend's `loc` metric for its `eloc`, which counts only the distinct
// non-comment lines inside the bodies of the class's own methods. Constants,
// properties, signature lines, the class braces, and abstract methods all
// contribute nothing. The body of an anonymous class declared inside a method
// does count, though — those lines sit inside the enclosing method's own token
// range — while the anonymous class's methods are not methods of the outer
// class and are never counted a second time in their own right.

class Ledger
{
    public const CURRENCY = 'USD';

    private int $balance = 0;

    public function credit(int $amount): void
    {
        // A comment-only line counts towards loc but never towards eloc.

        $this->balance += $amount; // A trailing comment leaves the line executable.
        /* A block comment
           spanning two lines. */
    }

    public function balance(): int
    {
        return $this->balance;
    }
}

abstract class Payment
{
    abstract public function amount(): int;

    public function describe(): string
    {
        return (string) $this->amount();
    }
}

class Factory
{
    public function make(): object
    {
        return new class {
            public function inner(): int
            {
                return 1;
            }
        };
    }
}
