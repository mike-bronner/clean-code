<?php

// Fixable: a literal beside a variable, in either order.
$simpleLeft = 'Hello ' . $name;
$simpleRight = $name . ' world';
$doubleQuoted = "Count: " . $total;

// Load-bearing: the fixer must brace the variable, or "{$name}end" collapses
// into the undefined variable $nameend.
$adjacent = $name . 'end';

// Load-bearing: an unescaped trailing `$` would fuse with the injected braces
// into the deprecated `${...}` dollar-curly syntax and change the value.
$trailingDollar = "Total $" . $x;

// Fixable: any number of operands, and any variable expression. Each variable
// is braced, so `{$a}{$b}` cannot run two names together and `{$user->name}s`
// cannot swallow the trailing character. An operand that already interpolates
// carries its own braces across untouched.
$chain = 'a' . $first . 'b' . $last;
$property = 'user: ' . $user->name;
$index = 'item: ' . $items['key'];
$method = 'result: ' . $service->run();
$interpolatedOperand = "Hello {$a}" . $b;

// Detection-only: grouping parentheses are transparent to the standard, so
// these have to be reported. Before operandStart() guarded its step past a
// bare `(`, the operand boundary landed on the assignment operator and the
// whole chain was silently discarded — no violation of any kind.
//
// They stay unfixable after the fixer was widened to arbitrary chains: `{($b)}`
// is a brace followed by text, not a variable expression, so writing it would
// change the value. operandPointer() sees through the parentheses to classify
// the operand, so the fixer checks the raw start instead.
$parenthesized = ($b) . 'y';
$doubleParenthesized = (($c)) . 'x';
$spacedParenthesized = ( $d ) . 'q';

// Detection-only, and load-bearing: an uppercase binary-string prefix stays in
// the token's content, and has no fixable form — this fixer's output always
// interpolates, and PHP_CodeSniffer cannot read `B"…{$sum}…"`: it types the
// `B"` opener T_NONE and swallows the rest of the statement, and the source
// after it, into one bogus string token. Both were fixed until this refusal.
$binaryDouble = B"Total: " . $sum;
$binarySingle = B'Total: ' . $sum;

// Detection-only, and load-bearing: a grouping parenthesis with a member,
// index, or call chain hanging off its closer. operandPointer() bounded its
// unwrapping walks by the *operand's* end, which a chain runs past — so the
// wrapped token never matched, the operand was classified non-interpolatable,
// and these reported nothing at all while their unparenthesized twins on lines
// 18-20 reported. Each one pairs with the twin directly above it.
$parenthesizedProperty = ($user)->name . 'x';
$parenthesizedIndex = ($items)['key'] . 'x';
$parenthesizedMethod = 'result: ' . ($service)->run();
