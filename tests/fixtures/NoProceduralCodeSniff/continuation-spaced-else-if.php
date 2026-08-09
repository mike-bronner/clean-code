<?php

declare(strict_types=1);

namespace App\Support;

if (PHP_INT_MAX > 0) {
    echo 'positive';
} else if (PHP_INT_MAX < 0) {
    echo 'negative';
} else if (PHP_INT_MAX === 0) {
    echo 'zero';
} else {
    echo 'unreachable';
}

$afterChain = 1;

$afterThat = 2;
