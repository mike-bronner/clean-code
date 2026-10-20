<?php

// Each string below is three lines of text, exactly maximumLines. Every line
// carries several characters whose UTF-8 encoding contains the byte 0x85, which
// a byte-mode \R reads as NEL and counts as a line break.

// Greek upsilon, U+03C5, encodes as CF 85.
$greek = "υπό λύκου υγεία\n"
    . "θυμός υψηλός υιός\n"
    . "ουρανός υπνος βυθός";

// Latin capital N with cedilla, U+0145, encodes as C5 85.
$latin = "Ņ ŅŅ Ņemt\n"
    . "ŅaŅbŅ\n"
    . "ŅŅŅ end";

// Four-per-em space, U+2005, encodes as E2 80 85. It is invisible: each line
// below carries two of them, between the letters.
$space = "a b c\n"
    . "d e f\n"
    . "g h i";
