<?php

declare(strict_types=1);

namespace App;

use App\Contracts\Notifier;
use App\Support\Clock;

class ReminderService
{
    public function __construct(private Notifier $notifier, private Clock $clock)
    {
    }

    public function remind(): void
    {
        $this->notifier->notify($this->clock->now());
    }
}
