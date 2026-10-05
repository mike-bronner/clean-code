<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Every pair below is a near miss: the sniff walks all of it and stays silent,
 * because each pair breaks the match before it reaches both defaults of five
 * lines and seventy tokens.
 *
 * Only variable names are set aside. A literal, a method name, or a class name
 * is compared as written, so a copy that changes any one of them is not a
 * repeat.
 */
class NearMisses
{
    /**
     * Four bodies of nine lines, braces included, well over seventy tokens
     * each. They differ only on the middle line: the shipping speed literal,
     * the method called, and the class instantiated. The lines either side of
     * it are four long, too short to match on their own, so the middle line
     * decides every pair.
     */
    public function ship(Order $order): string
    {
        $carrier = $this->carriers->for($order->region, $order->weight);
        $label = $carrier->label($order->address, $this->sender);
        $tracking = $carrier->book($label, $order->reference);
        $order->markShipped(new Shipment($tracking, 'standard'));
        $this->notifier->send($order->customer, new Shipped($tracking));
        $this->metrics->increment('orders.shipped');

        return $tracking;
    }

    public function shipExpress(Order $parcel): string
    {
        $carrier = $this->carriers->for($parcel->region, $parcel->weight);
        $label = $carrier->label($parcel->address, $this->sender);
        $tracking = $carrier->book($label, $parcel->reference);
        $parcel->markShipped(new Shipment($tracking, 'express'));
        $this->notifier->send($parcel->customer, new Shipped($tracking));
        $this->metrics->increment('orders.shipped');

        return $tracking;
    }

    public function dispatch(Order $consignment): string
    {
        $carrier = $this->carriers->for($consignment->region, $consignment->weight);
        $label = $carrier->label($consignment->address, $this->sender);
        $tracking = $carrier->book($label, $consignment->reference);
        $consignment->markDispatched(new Shipment($tracking, 'standard'));
        $this->notifier->send($consignment->customer, new Shipped($tracking));
        $this->metrics->increment('orders.shipped');

        return $tracking;
    }

    public function deliver(Order $package): string
    {
        $carrier = $this->carriers->for($package->region, $package->weight);
        $label = $carrier->label($package->address, $this->sender);
        $tracking = $carrier->book($label, $package->reference);
        $package->markShipped(new Delivery($tracking, 'standard'));
        $this->notifier->send($package->customer, new Shipped($tracking));
        $this->metrics->increment('orders.shipped');

        return $tracking;
    }

    /**
     * Two bodies that match token for token apart from their variable names,
     * over seven lines. They hold too few tokens to reach the seventy-token
     * default, so the repeat is not reported.
     */
    private function openLedger(): Ledger
    {
        $ledger = $this->ledgers->open();
        $ledger->lock();
        $ledger->rewind();
        $this->audit($ledger);

        return $ledger;
    }

    private function openJournal(): Ledger
    {
        $journal = $this->ledgers->open();
        $journal->lock();
        $journal->rewind();
        $this->audit($journal);

        return $journal;
    }

    /**
     * Nothing here repeats anything: kept as the plain negative case, so the
     * fixture is not made entirely of pairs.
     */
    private function describe(Order $order): string
    {
        return sprintf(
            '%s/%d (%s)',
            $order->reference(),
            $order->total(),
            implode('|', $order->tags())
        );
    }
}
