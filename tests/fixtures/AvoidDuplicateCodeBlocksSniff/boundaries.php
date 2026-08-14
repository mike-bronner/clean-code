<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * The two sides of the minimumLines threshold, as identical pairs of five and
 * of four lines of code. Braces sit on their own lines under PSR-12 and are not
 * counted, so each pair is exactly as long as its name says.
 *
 * At the default of five only the first pair is reported; lowering the property
 * to four brings the second in as well.
 */
class Boundaries
{
    private function exportOrders(array $rows): void
    {
        $handle = $this->open();
        $writer = $this->writer($handle);
        $writer->head($rows);
        $writer->rows($rows);
    }

    private function exportInvoices(array $lines): void
    {
        $stream = $this->open();
        $printer = $this->writer($stream);
        $printer->head($lines);
        $printer->rows($lines);
    }

    private function tick(): void
    {
        $this->clock->advance();
    }

    private function warmCache(array $keys): void
    {
        $store = $this->store();
        $store->flush();
        $store->prime($keys);
    }

    private function warmIndex(array $names): void
    {
        $index = $this->index();
        $index->flush();
        $index->prime($names);
    }
}
