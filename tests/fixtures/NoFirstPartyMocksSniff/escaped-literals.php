<?php

namespace App\Tests\Unit;

// PHP's own lexer collapses a doubled separator in BOTH quote styles, so every
// literal below names App\Models\User. Read verbatim, the single-quoted one
// stays App\\Models\\User — a name no configured root of more than one segment
// can ever match, and one a single-segment root that matched it by prefix luck
// would repeat back doubled in the warning message.
$singleQuoted = Mockery::mock('App\\Models\\User');
$doubleQuoted = Mockery::mock("App\\Models\\User");

// A single separator already says what it means in a single-quoted literal, so
// it has to survive unchanged rather than collapse a second time.
$verbatim = Mockery::mock('App\Models\User');

// Under an App\Models root this class sits outside it, which is what separates
// a multi-segment root from the plain App the master ruleset ships.
$outsideTheRoot = Mockery::mock('App\Services\Payments');
