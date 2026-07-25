<?php

declare(strict_types=1);

// Deliberately unterminated. PHP_CodeSniffer tokenizes files mid-edit, and an
// accessor whose bracket never closes carries no `bracket_closer`; the sniff
// must still resolve a chain end rather than fall over, and must report the
// read rather than let it through.
$value = $payload['key'
$dynamic = $order->{


// An unterminated construct can also sit between a chain and whatever encloses
// it, and then the walk outward cannot skip it to reach the enclosing
// construct. It stops there and the read is reported, rather than trusting a
// closer it cannot prove belongs to the same construct: below, continuing the
// walk would find the pattern's `]` and take $collected for a destructuring
// target, dropping the read on a statement PHP rejects anyway.
[$collected['first'] . foo(] = $source;
