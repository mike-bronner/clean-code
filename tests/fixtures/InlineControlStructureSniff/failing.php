<?php

// Positive: braced if produces no violation.
if ($condition === true) {
    doSomething();
}

// Positive: braced if/else produces no violation.
if ($condition === true) {
    doSomething();
} else {
    doSomethingElse();
}

// Positive: braced if/elseif/else produces no violation.
if ($first === true) {
    doSomething();
} elseif ($second === true) {
    doOtherThing();
} else {
    doSomethingElse();
}

// Negative: inline if without braces.
if ($condition === true) doSomething();

// Negative: inline if with an inline else branch.
if ($condition === true) doSomething();
else doSomethingElse();

// Negative: inline if/elseif/else chain.
if ($first === true) doSomething();
elseif ($second === true) doOtherThing();
else doSomethingElse();

// Edge: nested inline conditionals on one line.
if ($outer === true) if ($inner === true) doSomething();

// Edge: inline conditional inside a foreach loop.
foreach ($items as $item) {
    if ($item === null) continue;
}

// Edge: inline conditional inside a while loop.
while ($running === true) {
    if ($count > 10) break;
}

// Edge: alternative control-structure syntax is not inline.
if ($condition === true):
    doSomething();
endif;

// Edge: alternative syntax with an else branch is not inline.
if ($condition === true):
    doSomething();
else:
    doSomethingElse();
endif;
