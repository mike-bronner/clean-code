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

// A literal spanning several physical lines is tokenized one token per line, so
// no single token holds it: the first opens the string, the last closes it.
// Rewriting either fragment on its own leaves the file unparseable, so this
// shape stays silent here and belongs to CleanCode.Strings.MultilineStrings.
// Both operand positions, because only one of them is the token the sniff
// reaches first.
$multilineLeft = 'line one
line two ' . $name;
$multilineRight = $name . 'line one
line two ';
