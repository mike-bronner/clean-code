<?php

$simpleLeft = 'Hello ' . $name;
$simpleRight = $name . ' world';
$doubleQuoted = "Count: " . $total;
$adjacent = $name . 'end';
$trailingDollar = "Total $" . $x;
$chain = 'a' . $first . 'b' . $last;
$property = 'user: ' . $user->name;
$index = 'item: ' . $items['key'];
$method = 'result: ' . $service->run();
$interpolatedOperand = "Hello {$a}" . $b;
