<?php

declare(strict_types=1);

// Inline usage — both operands on one line — is always compliant, including
// operators acting as call parameters.
$sum = 4 + 4;
$result = floor(4 + 4.1);
$product = $a * $b - $c;
$joined = "Hello" . ", world!";
$mask = $high << $shift | $low;

// Multi-line statements: every manipulation operator starts the new line.
$result = 4
    + 4;

$string = "Hello"
    . strtolower(", world!");

$math = $base
    - $discount
    * $quantity;

$flags = $isActive
    && $isVerified
    || $isAdmin;

$bits = $high
    << $shift
    | $low
    ^ $toggle
    & $keep;

// An operator alone on its own line still leads that line — compliant.
$spread = $first
    +
    $second;

// Unary sign and reference forms are not manipulation operators.
$negative = -5;
$positive = +5;
$byReference = &$original;
$notted = ~$bits;
