<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * One shape, three times. Every other fixture here carries a pair, where "the
 * copy" and "the block it repeats" are the whole story; three blocks are what
 * separate reporting a *relationship* from reporting each *location*.
 *
 * All three methods are six code lines of the same shape, renamed throughout —
 * different method names, variables, array keys, and multipliers. Each is
 * therefore reported on its own first line, naming the other two.
 *
 * Pairing blocks off instead would report two of the three: the first would
 * stay silent because nothing precedes it, and the second and third would each
 * point back at the first without ever mentioning each other.
 */
class ThreeCopies
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

        return $receipts;
    }
}
