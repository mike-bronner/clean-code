<?php

namespace App\Tests\Unit;

use App\Models\User;

// A first-party facade or contract that wraps a genuinely external service is
// the gray area the standard's doc names, and it takes the ordinary PHPCS
// per-line suppression rather than a sniff-specific escape hatch.

// phpcs:ignore CleanCode.Testing.NoFirstPartyMocks.Found
$suppressed = $this->createMock(User::class);

$disabled = Mockery::mock(User::class); // phpcs:ignore CleanCode.Testing.NoFirstPartyMocks.Found

// Keeps the assertion honest: the same constructs still report when they are
// not suppressed, so a sniff that had fallen silent altogether would fail here
// rather than pass.
$reported = $this->createMock(User::class);
$alsoReported = Mockery::mock(User::class);
