<?php

declare(strict_types=1);

namespace App;

// PSR-12 orders the imports as three groups: classes, then functions, then
// constants. Every group here is internally sorted, so only the cross-group
// comparison can report this file. The `use function` group sits above the
// class imports, which puts the first class import out of order.
use function array_map;
use function count;

use App\Contracts\Notifier;
use App\Support\Clock;

use const PHP_EOL;

class DigestService
{
    public function __construct(
        private Notifier $notifier,
        private Clock $clock
    ) {
    }

    /**
     * @param array<int, mixed> $entries
     */
    public function digest(array $entries): string
    {
        $lines = array_map('strval', $entries);

        return count($lines) . $this->notifier->notify($this->clock->now()) . PHP_EOL;
    }
}
