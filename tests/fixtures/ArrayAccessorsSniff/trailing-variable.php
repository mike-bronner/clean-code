<?php

declare(strict_types=1);

// A file being edited can end on a bare variable, with no accessor after it to
// classify. There is nothing to report -- the standard is about accessors, and
// this statement has none -- but the guard that returns here is what keeps the
// missing accessor from reaching readAccessCode(), which takes an int. Without
// it the sniff dies with a TypeError on the file instead of passing it.
//
// Deliberately the last line in the file: any token after the variable would be
// the accessor whose absence this pins.
$payload
