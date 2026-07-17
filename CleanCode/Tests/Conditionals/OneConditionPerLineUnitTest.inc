<?php

// Compliant: single condition on one line with its keyword.
if ($isActive) {
    doSomething();
}

// Compliant: single condition with nested function-call parentheses.
if (in_array($value, $allowed, true)) {
    doSomething();
}

// Compliant: multi-condition, each condition on its own line, operators leading.
if (
    $isActive
    && $hasLicense
    || $isAdmin
) {
    doSomething();
}

// Compliant: first condition on the keyword line, operator leading.
if ($isActive
    && $hasLicense
) {
    doSomething();
}

// Compliant: parenthesized group counts as a single condition part.
if (
    ($isActive || $isTrial)
    && $hasLicense
) {
    doSomething();
}

// Compliant: boolean operators inside nested call parentheses are not top-level.
if (evaluate($a && $b)) {
    doSomething();
}

// Compliant: single-condition while on one line.
while ($queue->isNotEmpty()) {
    $queue->pop();
}

// Compliant: single-condition for on one line.
for ($i = 0; $i < 10; $i++) {
    doSomething();
}

// Compliant: single-condition do-while on one line.
do {
    doSomething();
} while ($queue->isNotEmpty());

// Compliant: ternary expressions are out of scope for this sniff.
$label = $count > 1
    ? 'items'
    : 'item';

// Compliant: boolean operator inside a short-array value is not top-level.
if (in_array($value, [$a && $b, $c], true)) {
    doSomething();
}

// Violation: single condition split across lines.
if (
    $isActive
) {
    doSomething();
}

// Violation: single condition with a nested call split across lines.
if (in_array(
    $value,
    $allowed,
    true
)) {
    doSomething();
}

// Violation: multi-condition collapsed onto one line.
if ($isActive && $hasLicense) {
    doSomething();
}

// Violation: multi-condition with trailing operators.
if ($isActive &&
    $hasLicense ||
    $isAdmin
) {
    doSomething();
}

// Violation: single-condition while split across lines.
while (
    $queue->isNotEmpty()
) {
    $queue->pop();
}

// Violation: for-loop condition section split across lines.
for ($i = 0; $i <
    10; $i++) {
    doSomething();
}

// Violation: multi-condition for-loop condition collapsed onto one line.
for ($i = 0; $i < 10 && $ok; $i++) {
    doSomething();
}

// Violation: do-while condition split across lines.
do {
    doSomething();
} while (
    $queue->isNotEmpty()
);

// Violation: multi-condition elseif collapsed onto one line.
if ($isActive) {
    doSomething();
} elseif ($hasLicense && $isAdmin) {
    doSomething();
}

// Violation: split single condition containing a comment is reported but not auto-fixed.
if (
    // still checking
    $isActive
) {
    doSomething();
}
