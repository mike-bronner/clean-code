<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * One pair of eight-line blocks, braces included, that match token for token
 * apart from their variable names, at well under the default token minimum.
 * Eight lines clears the default line minimum, so the token minimum alone
 * decides whether the pair is reported.
 *
 * Each block holds 47 tokens, counting every token on its lines: a token
 * minimum of 47 reports the pair, and 48 does not.
 */
class TokenMinimum
{
    public function archiveOrders(array $rows): int
    {
        $batch = $this->batches->start();
        $batch->append($rows);
        $batch->compress();
        $batch->seal();
        $this->archive->store($batch);

        return $batch->size();
    }

    public function archiveInvoices(array $lines): int
    {
        $bundle = $this->batches->start();
        $bundle->append($lines);
        $bundle->compress();
        $bundle->seal();
        $this->archive->store($bundle);

        return $bundle->size();
    }
}
