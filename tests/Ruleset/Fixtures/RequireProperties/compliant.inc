<?php

declare(strict_types=1);

namespace App\Fixtures;

// Each class encapsulates state, so none is flagged: an instance property, a
// static property, and a constructor-promoted property all satisfy the rule.
class Account
{
    private int $balance = 0;

    public function balance(): int
    {
        return $this->balance;
    }
}

class Counter
{
    public static int $count = 0;
}

class Money
{
    public function __construct(private int $amount)
    {
    }
}
