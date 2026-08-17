<?php

namespace App\Tests\Unit;

use App\Models\User;
use Vendor\Sdk\Client;

// Line 10 warns under the App root the master ruleset configures and line 11
// does not; replacing $firstPartyNamespaces with Vendor swaps the verdicts.
$firstParty = $this->createMock(User::class);
$vendor = $this->createMock(Client::class);

// Line 15 stays silent because `double` is not a configured mock creator;
// replacing $mockCreators with it is what makes the line report.
$notACreator = $this->double(User::class);
