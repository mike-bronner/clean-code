<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * One shape, three times. Every other fixture here carries a pair, where "the
 * copy" and "the block it repeats" are the whole story; three blocks are what
 * separate reporting a *relationship* from reporting each *location*.
 *
 * All three method bodies are seven lines, braces included, that match token
 * for token apart from their variable names. The method names differ, so each
 * block starts at the opening brace of its body. Each is reported there,
 * naming the other two.
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
        $invoices['total'] = $size * 2;
        $invoices['first'] = $lines[0];

        return $invoices;
    }

    public function importReceipts(array $entries): array
    {
        $receipts = [];
        $length = count($entries);
        $receipts['total'] = $length * 2;
        $receipts['first'] = $entries[0];

        return $receipts;
    }
}
