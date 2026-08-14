<?php

declare(strict_types=1);

namespace App;

use App\Contracts\Notifier;
use App\Support\Clock;
// Aliased import, deliberately placed by its fully qualified name: Timezone
// sorts after Clock, while the alias Amsterdam would sort before it. A sniff
// that compared aliases instead of real names would flag this line.
use App\Support\Timezone as Amsterdam;

// A commented, blank-line separated block. Separation is cosmetic: the sniff
// reads every import in the file as one list, so these still have to sort
// after the App\* entries above.
use Illuminate\Support\Collection;

use function array_map;
use function count;

use const PHP_EOL;
use const SORT_STRING;

class ReminderService
{
    public function __construct(
        private Notifier $notifier,
        private Clock $clock,
        private Amsterdam $timezone
    ) {
    }

    public function remind(Collection $recipients): string
    {
        $names = array_map('strval', $recipients->sort(SORT_STRING)->all());

        return count($names) . $this->notifier->notify($this->clock->now(), $this->timezone) . PHP_EOL;
    }
}
