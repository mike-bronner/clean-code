<?php

declare(strict_types=1);

// Seeded so the master ruleset's undefined-variable rule (#85) stays quiet:
// this fixture exists to pin operator diagnostics, not variable definedness.
$a = $b = $first = $second = $ready = $enabled = true;

$sum = 1+2;
$joined = $a.'b';
$padded  = 3;

$wrapped = $first .
    $second;

if (
    $ready ||
    $enabled
) {
    $ok = true;
}

if ( ! $ready) {
    $stop = true;
}

if (
    $a ===
    $b
    || $ready
) {
    $done = true;
}

$total = $first -
    $second;

if (
    $first +
    $second > $total
) {
    $reached = true;
}
