<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Three blocks of one shape where the third stops matching a line early.
 *
 * Each body matches the others token for token apart from its variable names,
 * until its return. The first two run to eight lines together, braces
 * included; the third shares only the first six, because it returns a wrapped
 * array rather than the variable. The extent the warning names is therefore
 * six lines, for all three — the span every named location actually holds.
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
        $orders['last'] = $rows[1];

        return $orders;
    }

    public function importInvoices(array $lines): array
    {
        $invoices = [];
        $size = count($lines);
        $invoices['total'] = $size * 2;
        $invoices['first'] = $lines[0];
        $invoices['last'] = $lines[1];

        return $invoices;
    }

    public function importReceipts(array $entries): array
    {
        $receipts = [];
        $length = count($entries);
        $receipts['total'] = $length * 2;
        $receipts['first'] = $entries[0];
        $receipts['last'] = $entries[1];

        return [$receipts];
    }
}
