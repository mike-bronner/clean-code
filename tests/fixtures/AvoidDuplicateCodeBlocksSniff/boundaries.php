<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * The two sides of the minimumLines threshold, as pairs of five and of four
 * lines that match token for token apart from their variable names. Every
 * line counts, the braces included. The method names differ, so each block
 * runs from the opening brace to the closing one: three statements and two
 * braces in the first pair, two statements and two braces in the second.
 *
 * Neither pair reaches the default token minimum, so the tests lower it. At the
 * default of five lines only the first pair is reported; lowering the property
 * to four brings the second in as well.
 */
class Boundaries
{
    private function exportOrders(array $rows): void
    {
        $writer = $this->writer($this->open());
        $writer->rows($rows);
        $writer->close();
    }

    private function exportInvoices(array $lines): void
    {
        $printer = $this->writer($this->open());
        $printer->rows($lines);
        $printer->close();
    }

    private function tick(): void
    {
        $this->clock->advance();
    }

    private function warmCache(array $keys): void
    {
        $this->store()->flush();
        $this->store()->prime($keys);
    }

    private function warmIndex(array $names): void
    {
        $this->store()->flush();
        $this->store()->prime($names);
    }
}
