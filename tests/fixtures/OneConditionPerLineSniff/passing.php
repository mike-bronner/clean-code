<?php

// Positive: single condition on one line with its keyword.
if ($isActive) {
    doSomething();
}

// Positive: single condition carrying nested function-call parentheses.
if (in_array($value, $allowed, true)) {
    doSomething();
}

// Positive: multi-condition, each condition on its own line, operators leading.
if (
    $isActive
    && $hasLicense
    || $isAdmin
) {
    doSomething();
}

// Positive: first condition on the keyword line, operator leading.
if ($isActive
    && $hasLicense
) {
    doSomething();
}

// Positive: a parenthesized group counts as a single condition part.
if (
    ($isActive || $isTrial)
    && $hasLicense
) {
    doSomething();
}

// Positive: boolean operators inside nested call parentheses are not top-level.
if (evaluate($a && $b)) {
    doSomething();
}

// Positive: single-condition while on one line.
while ($queue->isNotEmpty()) {
    $queue->pop();
}

// Positive: multi-condition while, operators leading.
while (
    $queue->isNotEmpty()
    && $attempts < $limit
) {
    $queue->pop();
}

// Positive: elseif chains follow the same rule as if.
if ($isActive) {
    doSomething();
} elseif (
    $hasLicense
    && $isAdmin
) {
    doOtherThing();
} else {
    doSomethingElse();
}

// Positive: a for statement's three clauses are not boolean conditions.
for ($index = 0; $index < $count; $index++) {
    handle($index);
}
