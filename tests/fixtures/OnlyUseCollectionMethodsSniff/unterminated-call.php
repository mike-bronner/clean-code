<?php

declare(strict_types=1);

$items = collect([1, 2, 3]);
$total = count($items);

$remaining = count($items->filter(fn (int $number): bool => $number > 1
