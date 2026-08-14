<?php

declare(strict_types=1);

namespace App;

// The class imports lead correctly, so the class/function half of the PSR-12
// group order is satisfied here and cannot be what reports this file. The last
// two groups are swapped instead — constants above functions — which puts the
// first function import out of order and leaves only the function/constant
// half of the group order able to catch it.
use App\Contracts\Notifier;
use App\Support\Clock;

use const PHP_EOL;
use const SORT_STRING;

use function array_map;
use function count;

class SummaryService
{
    public function __construct(
        private Notifier $notifier,
        private Clock $clock
    ) {
    }

    /**
     * @param array<int, mixed> $entries
     */
    public function summarize(array $entries): string
    {
        $lines = array_map('strval', $entries);
        sort($lines, SORT_STRING);

        return count($lines) . $this->notifier->notify($this->clock->now()) . PHP_EOL;
    }
}
