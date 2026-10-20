<?php

// LF, CRLF and a lone CR each count as one line break: four lines, reported.
$lf = "one\n" . "two\n" . "three\n" . "four";
$crlf = "one\r\n" . "two\r\n" . "three\r\n" . "four";
$cr = "one\r" . "two\r" . "three\r" . "four";

// Each of these is three lines of text by LF, plus one more character between
// "three" and "four". U+0085 (NEL, encoded C2 85) counts as a line break, so
// that string is four lines and reported. U+2028 and U+2029 do not count, so
// those strings stay at three lines and are not reported.
$nel = "one\n" . "two\n" . "threefour";
$lineSeparator = "one\n" . "two\n" . "three four";
$paragraphSeparator = "one\n" . "two\n" . "three four";
