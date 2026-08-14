<?php

// Already interpolated — the shape the sniff steers code towards.
$interpolated = "Hello {$name}, welcome";

// Near misses: every one of these concatenations has an operand that cannot
// appear inside an interpolated string, so there is no single-string form to
// rewrite them into and the sniff must stay silent.
$constant = 'version-' . PHP_EOL;
$functionCall = 'name: ' . strtoupper($code);
$magicConstant = __DIR__ . '/config.php';
$staticCall = 'id: ' . Uuid::make();

// Parenthesized *compound* expressions. These open with a variable token just
// as `($b)` does, but "{$count + 1}" and "{$x . $y}" are not valid PHP, so the
// paren-unwrapping in operandCode() must not reach them.
$arithmetic = 'total: ' . ($count + 1);
$nestedConcat = 'sum: ' . ($x . $y);

// One of each kind is required before interpolation is even a question.
$literalOnly = 'foo' . 'bar';
$variablesOnly = $first . $last;
