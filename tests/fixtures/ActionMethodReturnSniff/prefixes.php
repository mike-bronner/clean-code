<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ActionMethodReturn;

/**
 * Verbs no shipped default carries. The file reports nothing out of the box and
 * reports both methods once a project configures its own list, which is what
 * tells a working $actionPrefixes property apart from a sniff that flags
 * everything.
 */
class Ledger
{
    public function publishEntry(): bool
    {
        return true;
    }

    public function archiveEntry(): bool
    {
        return true;
    }

    /**
     * Still silent under the configured list: `published` continues the word,
     * so the boundary rule holds however the list is tuned.
     */
    public function publishedEntries(): array
    {
        return [];
    }
}
