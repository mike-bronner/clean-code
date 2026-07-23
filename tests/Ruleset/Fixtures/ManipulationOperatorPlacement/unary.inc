<?php

declare(strict_types=1);

// Unary / reference operators carry no left-hand operand, so even when a
// newline sits between the operator and its operand they are not manipulation
// operators and must not be flagged.
$value = -
    5;

$positive = +
    5;

$reference = &
    $original;

// A binary operator that already leads is compliant even next to a unary sign.
$total = $base
    + -$adjustment;

// A reference `&` is not a bitwise-AND manipulation operator, even when a
// newline follows it after a type name (which is itself an operand terminator)
// or an opening `use (` — the reference is recognised by context, not by the
// preceding token.
function withReference(
    int &
        $ref
): void {
    //
}

$closure = function () use (&
    $captured) {
    //
};
