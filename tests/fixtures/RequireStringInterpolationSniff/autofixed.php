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

// Fixable, and load-bearing: an uppercase binary-string prefix stays inside the
// token's content, so the literal's first character is `B`, not its delimiter.
// Reading the delimiter off that character sends a double-quoted literal down
// the single-quoted branch, which escapes the real opening quote into the
// value: `B"Count: " . $n` came out as `"\"Count: {$n}"`.
$binaryDouble = B"Total: {$sum}";
$binarySingle = B"Total: {$sum}";
