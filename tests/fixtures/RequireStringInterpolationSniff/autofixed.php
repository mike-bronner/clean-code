<?php

// Fixable: exactly one plain literal plus one plain variable.
$simpleLeft = "Hello {$name}";
$simpleRight = "{$name} world";
$doubleQuoted = "Count: {$total}";

// Load-bearing: the fixer must brace the variable, or "{$name}end" collapses
// into the undefined variable $nameend.
$adjacent = "{$name}end";

// Load-bearing: an unescaped trailing `$` would fuse with the injected braces
// into the deprecated `${...}` dollar-curly syntax and change the value.
$trailingDollar = "Total \${$x}";

// Detection-only: interpolatable, but not a mechanical single-string rewrite.
$chain = 'a' . $first . 'b' . $last;
$property = 'user: ' . $user->name;
$index = 'item: ' . $items['key'];
$method = 'result: ' . $service->run();
$interpolatedOperand = "Hello {$a}" . $b;

// Detection-only: grouping parentheses are transparent to the standard, so
// these have to be reported. Before operandStart() guarded its step past a
// bare `(`, the operand boundary landed on the assignment operator and the
// whole chain was silently discarded — no violation of any kind.
$parenthesized = ($b) . 'y';
$doubleParenthesized = (($c)) . 'x';
$spacedParenthesized = ( $d ) . 'q';
