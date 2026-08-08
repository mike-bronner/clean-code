<?php

// A for loop's condition, the shape PHPMD's documentation uses.
for ($i = 0; $i < count($items); $i++) {
    echo $items[$i];
}

// sizeof() is the same function under its alias, and PHPMD names both.
for ($i = 0; $i < sizeof($items); $i++) {
    echo $items[$i];
}

// A while loop's condition is the whole parenthesised expression.
while (count($queue) > 0) {
    array_pop($queue);
}

while (sizeof($queue) > 0) {
    array_pop($queue);
}

// A do-while's condition hangs off the trailing while, which is where the
// violation is reported — PHPMD reports the same loop against its `do` line.
do {
    array_pop($stack);
} while (count($stack) > 0);

// Nested inside a compound condition rather than being the whole of it.
while (count($items) > 0 && $enabled) {
    array_pop($items);
}

for ($i = 0; $enabled && $i < count($items); $i++) {
    echo $items[$i];
}

// Two calls in one condition are two separate violations.
while (count($left) > 0 || count($right) > 0) {
    array_pop($left);
    array_pop($right);
}

// Each loop of a nest is judged on its own condition.
for ($i = 0; $i < count($rows); $i++) {
    for ($j = 0; $j < count($columns); $j++) {
        echo $rows[$i][$j];
    }
}

// Alternative syntax puts the condition in the same place.
while (count($items) > 0):
    array_pop($items);
endwhile;

// PHP function names are case-insensitive, so this is the same call.
for ($i = 0; $i < COUNT($items); $i++) {
    echo $items[$i];
}

// A leading namespace separator names the global function explicitly.
for ($i = 0; $i < \count($items); $i++) {
    echo $items[$i];
}

while (\sizeof($queue) > 0) {
    array_pop($queue);
}

// Nested inside a call in the condition: still re-evaluated every iteration.
while (max(0, count($items)) > 0) {
    array_pop($items);
}

while (array_filter($rows, static fn (array $row): bool => count($row) > 0)) {
    array_pop($rows);
}

// An arrow function in the initialiser is given a scope_closer pointing at the
// header's own first separator. A separator scan that jumps to it loses the
// condition section and misses this count() entirely.
for ($i = 0, $identity = fn (int $n): int => $n; $i < count($items); $i++) {
    echo $identity($i);
}

// A closure body in the initialiser, with the violation in the real condition.
for ($i = 0, $fn = function (): int {
    $seed = 1;
    return $seed;
}; $i < count($items); $i++) {
    echo $fn();
}
