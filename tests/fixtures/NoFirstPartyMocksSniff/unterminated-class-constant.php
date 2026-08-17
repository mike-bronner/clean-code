<?php

namespace App\Tests\Unit;

use App\Models\User;

$intact = $this->createMock(User::class);
$alsoIntact = Mockery::mock(User::class);
$truncated = $this->createMock(User::