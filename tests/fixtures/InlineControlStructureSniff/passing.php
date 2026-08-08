<?php

// Positive: braced if.
if ($condition === true) {
    doSomething();
}

// Positive: braced if/else.
if ($condition === true) {
    doSomething();
} else {
    doSomethingElse();
}

// Positive: braced if/elseif/else chain.
if ($first === true) {
    doSomething();
} elseif ($second === true) {
    doOtherThing();
} else {
    doSomethingElse();
}

// Positive: braced loops of every shape the sniff registers.
foreach ($items as $item) {
    handle($item);
}

for ($index = 0; $index < $count; $index++) {
    handle($index);
}

while ($queue->isNotEmpty()) {
    $queue->pop();
}

do {
    $attempts++;
} while ($attempts < $limit);

// Positive: nested braced conditionals.
if ($outer === true) {
    if ($inner === true) {
        doSomething();
    }
}

// Positive: a braced conditional inside a loop body.
foreach ($items as $item) {
    if ($item === null) {
        continue;
    }

    handle($item);
}

// Positive: switch is a braced structure by construction.
switch ($mode) {
    case 'read':
        read();
        break;
    default:
        skip();
        break;
}
