<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

class Formatter
{
    public function present(): string
    {
        return namespace\Repositories\present() . UserRepository::class;
    }
}

class LedgerRepository
{
}

class Ledger
{
}
