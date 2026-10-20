<?php

// The same characters as utf8-at-limit.php, one line over maximumLines. Each
// string is four lines of text and is reported once, stating four lines.

$greek = "υπό λύκου υγεία\n"
    . "θυμός υψηλός υιός\n"
    . "ουρανός υπνος βυθός\n"
    . "ύμνος υφή";

$latin = "Ņ ŅŅ Ņemt\n"
    . "ŅaŅbŅ\n"
    . "ŅŅŅ end\n"
    . "Ņ Ņ";

// Each line carries two invisible U+2005 four-per-em spaces.
$space = "a b c\n"
    . "d e f\n"
    . "g h i\n"
    . "j k";
