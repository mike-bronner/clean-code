<?php

// An unterminated index inside an array that does close. PHP_CodeSniffer
// leaves the `[` without a bracket_closer, so the walk cannot tell where the
// element it opened ends: reading on would take the comma inside the index for
// an element separator and report `'k' => 2` as a duplicate of `'k' => 1`. The
// array is abandoned instead.
$broken = array('k' => 1, 'j' => $offsets[0, 'k' => 2);
