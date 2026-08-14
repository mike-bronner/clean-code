<?php

// Positive: the size is taken once, before the loop, and the loop reads the
// variable. This is the rewrite the rule asks for.
$total = count($items);

for ($i = 0; $i < $total; $i++) {
    echo $items[$i];
}

$remaining = sizeof($queue);

while ($remaining > 0) {
    $remaining--;
}

$pending = count($jobs);

do {
    $pending--;
} while ($pending > 0);

// Positive: count() in a loop *body* is re-evaluated too, but it is not a loop
// condition, and hoisting it is not always correct — so the rule stays silent.
// These sit inside the very tokens the sniff registers on, so a sniff that
// scanned the whole loop rather than its condition would report them.
while ($cursor->valid()) {
    $size = count($cursor->current());
    $cursor->next();
}

for ($i = 0; $i < $total; $i++) {
    $width = sizeof($items[$i]);
}

do {
    $depth = count($stack);
    array_pop($stack);
} while ($depth > 1);

// Positive: foreach has no condition expression at all.
foreach ($collections as $collection) {
    echo count($collection);
}

// Positive: count() outside any loop.
if (count($items) > 0) {
    echo 'not empty';
}

$size = count($items);
$callback = 'count';
