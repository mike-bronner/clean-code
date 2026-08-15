<?php

declare(strict_types=1);

$test = true;

if (! $test) {
    echo 'no space after';
}

$flags = [! $test];

if (! $test) {
    echo 'too much space after';
}

$value = $data[! $test];
