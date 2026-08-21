<?php

class Ledger
{
    private $owner;

    /**
     * @var int
     */
    private int $balance = 0;

    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @param int $amount
     */
    public function deposit(int $amount): void
    {
        $this->balance += $amount;
    }

    public function withdraw(int $amount)
    {
        $this->balance -= $amount;

        return $this->balance;
    }

    /**
     * @return int
     */
    public function balance(): int
    {
        return $this->balance;
    }

    public function tag(...$labels): void
    {
        $this->owner .= implode(',', $labels);
    }
}

class Wallet
{
    public function __construct(private $owner)
    {
    }
}

interface Registry
{
    public function all();
}

class Locator
{
    /**
     * @param int|null $id
     */
    public function locate(?int $id): void
    {
    }
}
