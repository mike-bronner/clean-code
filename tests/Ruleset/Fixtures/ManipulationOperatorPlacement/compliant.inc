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

// The remaining math operators wrap the same way.
$scaled = $total
    / $count
    % $limit
    ** $power;

// A wrapped operator inside a control-structure condition, a call-argument
// list, or an array literal is compliant when it leads its continuation line.
if (
    $isActive
    && $isVerified
) {
    //
}

$between = clamp(
    $low
    + $high
);

$list = [
    $base
    - $discount,
];

// A `|` separating exception types in a multi-line catch clause is a type
// union, not a bitwise manipulation operator, so it is never flagged even when
// it trails — the reformatting the fixer would otherwise apply is suppressed.
try {
    //
} catch (RuntimeException |
    LogicException $error) {
    //
}

// Unary sign and reference forms are not manipulation operators.
$negative = -5;
$positive = +5;
$byReference = &$original;
$notted = ~$bits;
