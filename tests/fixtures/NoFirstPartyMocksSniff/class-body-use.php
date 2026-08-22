<?php

namespace App\Tests\Unit;

// The import walk stops at the first token that cannot belong to a file header,
// so the trait `use` below is never read as a class import. Were it read, the
// alias `Client` would resolve to Vendor\Sdk\Client and the mock on the last
// line would be treated as a vendor class and stay silent.
class Doubles
{
    use \Vendor\Sdk\Client;
}

$mock = $this->createMock(Client::class);
