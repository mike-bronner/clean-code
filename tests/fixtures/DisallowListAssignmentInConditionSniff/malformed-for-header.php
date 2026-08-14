<?php

// A for header with no section separators. It is not valid PHP, but the
// tokenizer still pairs the parentheses and names the "for" as their owner,
// so the sniff reaches its section test on this. With no separators there is
// no condition section to sit in, and the sniff must say so rather than
// invent one.

for (list($first, $second) = $data) {
    unset($first, $second);
}

// One separator instead of two, with the assignment on either side of it.

for (list($third, $fourth) = $data; $third < 3) {
    unset($fourth);
}

for ($cursor = 0; list($fifth, $sixth) = $data) {
    unset($cursor, $fifth, $sixth);
}
