<?php

// Positive: an argument list holds terms, not the line's thought.
format($this->user->name, $this->user->email);

// Positive: an array literal renders one item per row.
$rows = [
    $this->user->name,
    $this->order->total,
];

// Positive: a statement condition is one group, however many terms it holds.
if ($this->calls->isGlobal($file, $ptr) === false) {
    return;
}

// Positive: nesting does not change the answer.
$mapped = array_merge($first->all(), [$this->order->total]);

// Negative: a bare chain at statement level is still the line's thought.
$this->fixer->beginChangeset();

// Negative: an assignment's right-hand chain is not inside any group.
$value = $this->order->total;

// Negative: a closure body is inside the closure, not inside the argument
// list the closure was passed to.
array_map(function ($row) {
    return $this->order->total;
}, $rows);

// Negative: a group that closes before the operator does not enclose it.
$total = wrap($cart)->order->total;
