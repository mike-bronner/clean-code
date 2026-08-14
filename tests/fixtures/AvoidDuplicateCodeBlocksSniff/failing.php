<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

class ReportBuilder
{
    /**
     * Two copy-pasted blocks inside one declaration. Nothing here is a
     * duplicated *body*, so a whole-declaration comparison would see nothing:
     * the duplication is a run of lines in the middle of one method.
     *
     * The copy is near-identical rather than identical — the accumulator, the
     * loop variable, and the array keys were all renamed, and the multiplier
     * changed. Dropping token content from the comparison is what still lets
     * these two match.
     */
    public function build(array $rows): array
    {
        $paid = [];

        foreach ($rows as $row) {
            $amount = $row['total'] * 100;
            $paid[] = [
                'id' => $row['id'],
                'cents' => $amount,
            ];
        }

        $due = [];

        foreach ($rows as $entry) {
            $value = $entry['balance'] * 1000;
            $due[] = [
                'key' => $entry['ref'],
                'units' => $value,
            ];
        }

        return [$paid, $due];
    }
}

class InvoiceGateway
{
    /**
     * The whole-body case the block comparison still covers: two method bodies
     * of the same shape, differing only in the names of the methods they call,
     * the class they instantiate, and their literals.
     */
    public function charge(int $customer): string
    {
        $client = new StripeClient($customer);
        $reference = $client->authorize('usd');
        $client->capture($reference);
        $this->log('charged', $reference);

        return $reference;
    }

    public function refund(int $account): string
    {
        $gateway = new PaypalClient($account);
        $receipt = $gateway->reverse('eur');
        $gateway->settle($receipt);
        $this->log('refunded', $receipt);

        return $receipt;
    }

    private function log(string $event, string $reference): void
    {
        error_log($event . ':' . $reference);
    }
}
