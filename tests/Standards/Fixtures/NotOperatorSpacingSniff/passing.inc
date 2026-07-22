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

return ! $test;
