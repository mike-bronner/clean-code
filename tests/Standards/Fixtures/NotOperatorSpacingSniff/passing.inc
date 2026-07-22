<?php

declare(strict_types=1);

$test = true;

if (! $test) {
    echo 'compliant';
}

$result = ! $test;

$values = [! $test];

while (! $test) {
    break;
}

// Padding after an opening parenthesis is PSR-12's to police, not this
// sniff's — so this sniff stays silent on it even though the space is there.
if ( ! $test) {
    echo 'paren padding deferred to PSR-12';
}

return ! $test;
