<?php

// PHP 8.1's first-class callable syntax. `count(...)` and `sizeof(...)` build a
// Closure that *refers* to the function; neither one invokes it, so no array is
// counted and there is no per-iteration re-count for the rule to catch. The
// name and the opening parenthesis sit exactly where a real call would, so the
// only thing keeping this file silent is the first-class-callable check: drop
// it and every loop below is reported.
//
// composer.json requires php ^8.1, so this syntax is in-language here.
//
// PHPMD 2.15.0 flags every loop below — it matches the AST's FunctionPostfix
// node without inspecting the argument list. That is a false positive, so this
// is a deliberate, documented divergence in the looser direction.

$items = [];
$callables = [];

while ($sizer = count(...)) {
    break;
}

while ($sizer = sizeof(...)) {
    break;
}

// Nested inside another call in the condition — the shape the depth-walking
// scan is built to walk into, so it reaches this the same way it reaches a real
// nested count().
while (in_array(count(...), $callables, true)) {
    break;
}

// The middle section of a `for` header: the one section the sniff judges.
for ($i = 0; $i < 1 && count(...); $i++) {
    break;
}

// Comments and whitespace between the name and the ellipsis do not change what
// the expression is, and the scan skips them the same way it does for a call.
while ($sizer = count( /* first-class callable */ ... )) {
    break;
}
