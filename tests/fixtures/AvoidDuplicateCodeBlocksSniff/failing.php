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
     * The copy renames the accumulator, the source list, and the loop
     * variables, and nothing else. Every key, literal, and call is the same, so
     * the two blocks match token for token once variable names are set aside.
     * Each block is long enough to clear both defaults: five lines, seventy
     * tokens.
     */
    public function build(array $rows, array $refunds): array
    {
        $paid = [];

        foreach ($rows as $row) {
            $amount = $row['total'] * 100;
            $paid[] = [
                'id' => $row['id'],
                'cents' => $amount,
                'tax' => $amount * 0.2,
                'currency' => strtoupper($row['currency']),
                'settled' => $row['status'] === 'settled',
                'note' => trim($row['note'] ?? ''),
            ];
        }

        $returned = [];

        foreach ($refunds as $refund) {
            $value = $refund['total'] * 100;
            $returned[] = [
                'id' => $refund['id'],
                'cents' => $value,
                'tax' => $value * 0.2,
                'currency' => strtoupper($refund['currency']),
                'settled' => $refund['status'] === 'settled',
                'note' => trim($refund['note'] ?? ''),
            ];
        }

        return [$paid, $returned];
    }
}

class InvoiceGateway
{
    /**
     * The whole-body case: two method bodies that match token for token apart
     * from their variable names. The method names differ, so each block starts
     * at the opening brace of its body rather than at the signature.
     */
    public function charge(int $customer): string
    {
        $client = new StripeClient($customer, $this->secret);
        $client->setTimeout($this->timeout);
        $reference = $client->authorize('usd', $this->amount);
        $client->capture($reference, ['idempotent' => true]);
        $this->log('charged', $reference);
        $this->events->dispatch(new Charged($reference));

        return $reference;
    }

    public function recharge(int $account): string
    {
        $gateway = new StripeClient($account, $this->secret);
        $gateway->setTimeout($this->timeout);
        $receipt = $gateway->authorize('usd', $this->amount);
        $gateway->capture($receipt, ['idempotent' => true]);
        $this->log('charged', $receipt);
        $this->events->dispatch(new Charged($receipt));

        return $receipt;
    }

    private function log(string $event, string $reference): void
    {
        error_log($event . ':' . $reference);
    }
}
