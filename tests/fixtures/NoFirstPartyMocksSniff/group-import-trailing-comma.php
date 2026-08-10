<?php

namespace App\Tests\Unit;

// A trailing comma inside a group import is legal from PHP 8.0. It leaves an
// empty clause behind, and prefixing that clause before dropping it invents an
// alias the file never wrote: `sdk => Vendor\Sdk` from the first group and
// `models => App\Models` from the second.
use Vendor\Sdk\{Client, Server as Host,};
use App\Models\{Order, Invoice as Bill,};

// Both halves of both groups still import, trailing comma or not.
$order = $this->createMock(Order::class);
$invoice = $this->createMock(Bill::class);
$client = $this->createMock(Client::class);
$server = $this->createMock(Host::class);

// `Sdk` is imported by neither group, so it resolves inside the declared
// namespace as App\Tests\Unit\Sdk and reports under App. Through the phantom
// alias it would resolve to Vendor\Sdk and fall silent — and report under a
// Vendor root that must leave it alone.
$phantom = $this->createMock(Sdk::class);

// `Models` is not imported either, and the second group's phantom alias would
// bind it to App\Models — first-party under App as well, so only the resolved
// name the message carries separates the two.
$alsoPhantom = $this->createMock(Models::class);
