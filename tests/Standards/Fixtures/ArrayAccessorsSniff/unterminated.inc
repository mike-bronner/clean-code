<?php

declare(strict_types=1);

// Deliberately unterminated. PHP_CodeSniffer tokenizes files mid-edit, and an
// accessor whose bracket never closes carries no `bracket_closer`; the sniff
// must still resolve a chain end rather than fall over, and must report the
// read rather than let it through.
$value = $payload['key'
$dynamic = $order->{
