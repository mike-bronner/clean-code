<?php

// A for header has three sections and only the middle one is the loop's
// continuation test. This fixture holds count()/sizeof() in the other two, and
// nothing in the condition, so it isolates the section restriction: drop it and
// every call below is reported.

// The initialiser runs once, before the first iteration.
for ($i = 0, $length = count($rows); $i < $length; $i++) {
    echo $rows[$i];
}

for ($i = 0, $width = sizeof($columns); $i < $width; $i++) {
    echo $columns[$i];
}

// The increment runs after each iteration and is not the continuation test.
for ($i = 0; $i < 100; $i += count($step)) {
    echo $i;
}

for ($i = 0; $i < 100; $i += sizeof($step)) {
    echo $i;
}

// Both at once, with a variable condition between them.
for ($i = 0, $limit = count($rows); $i < $limit; $i += count($step)) {
    echo $rows[$i];
}
