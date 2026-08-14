<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Three blocks of one shape where the third stops matching a line early.
 *
 * The first two run to six code lines together; the third shares only the five
 * the window itself covers, because it returns a wrapped array rather than the
 * variable. The extent the warning names is therefore five lines, for all
 * three — the span every named location actually holds.
 *
 * Naming the extent the first pair reached would make the third warning claim a
 * line the third block does not share, which is the one thing a report pointing
 * a reader at two places must never do.
 */
class SharedExtent
{
    public function importOrders(array $rows): array
    {
        $orders = [];
        $count = count($rows);
        $orders['total'] = $count * 2;
        $orders['first'] = $rows[0];

        return $orders;
    }

    public function importInvoices(array $lines): array
    {
        $invoices = [];
        $size = count($lines);
        $invoices['sum'] = $size * 3;
        $invoices['head'] = $lines[0];

        return $invoices;
    }

    public function importReceipts(array $entries): array
    {
        $receipts = [];
        $length = count($entries);
        $receipts['net'] = $length * 4;
        $receipts['lead'] = $entries[0];

        return [$receipts];
    }
}
