<?php

declare(strict_types=1);

namespace App\Support;

if (PHP_INT_MAX > 0):
    echo 'positive';
elseif (PHP_INT_MAX < 0):
    echo 'negative';
else:
    echo 'zero';
endif;

foreach ([1, 2] as $value):
    echo $value;
endforeach;

$afterChain = 1;
