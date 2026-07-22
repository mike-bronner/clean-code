<?php

$interpolated = "Hello {$name}, welcome";
$constant = 'version-' . PHP_EOL;
$functionCall = 'name: ' . strtoupper($code);
$literalOnly = 'foo' . 'bar';
$magicConstant = __DIR__ . '/config.php';
