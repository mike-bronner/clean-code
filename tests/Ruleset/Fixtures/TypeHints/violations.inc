<?php

class Ledger
{
    private $owner;

    /**
     * @var int
     */
    private $balance = 0;

    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @param int $amount
     */
    public function deposit($amount): void
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
    public function balance()
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
