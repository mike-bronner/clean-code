<?php

namespace App\Tests\Unit;

// An import binds its alias to a whole namespace, so every segment written
// after the alias belongs to the resolved name too. Keeping only the alias
// resolves lines 17 and 18 to App\Models — shorter, wrong, and still under the
// shipped App root, so it still reports on the same line. Only a root that
// separates the two names can see the difference.
use App\Models;
use Vendor\Sdk;

// A leading separator is not part of the imported name. Kept, the alias binds
// to `\App\Enums\Status`, which no configured root can match.
use \App\Enums\Status;

$comment = $this->createMock(Models\Comment::class);
$draft = $this->createMock(Models\Comment\Draft::class);
$status = $this->createMock(Status::class);

// The same multi-segment shape through a vendor import. Silent under App
// whether or not the trailing segment survives, so the Vendor\Sdk\Client root
// is what pins it.
$client = $this->createMock(Sdk\Client::class);
