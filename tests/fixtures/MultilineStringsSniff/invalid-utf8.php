<?php

// Not valid UTF-8: the strings carry raw Latin-1 bytes, which is the
// shape a file saved in another encoding has. Escapes such as "\xCF"
// cannot stand in for them, because the sniff reads an escape as four
// ASCII characters. These strings are counted byte by byte, as before.

// Three LF breaks plus a lone 0x85 byte, which byte mode counts: five lines.
$over = "café one\n"
    . "two\n"
    . "three…four\n"
    . "five";

// Two LF breaks and no 0x85 byte: three lines, at the limit.
$atLimit = "café one\n"
    . "two\n"
    . "thrÏee";
