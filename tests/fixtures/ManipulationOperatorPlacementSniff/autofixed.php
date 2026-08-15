<?php

declare(strict_types=1);

// Math operators trailing the previous line instead of leading the next one.
$result = 4
    + 4;

$math = $base
    - $discount
    * $quantity;

$power = $base
    ** $exponent;

$mod = $total
    % $divisor;

$quotient = $total
    / $count;

// Bitwise operators, same rule.
$bits = $high
    << $shift
    >> $low;

$masked = $high
    | $low
    ^ $toggle
    & $keep;

// Trailing `+`/`-` off a value the operand model must recognise: magic
// constants, interpolated double-quoted strings, and heredoc/nowdoc bodies all
// end a real left-hand operand, so the operator genuinely trails and is flagged
// (never mistaken for a unary sign).
$magic = __LINE__
    - 1;

$interpolated = "id-$id"
    - 5;

$heredoc = <<<NUM
    41
    NUM
    - 1;

$nowdoc = <<<'NUM'
    41
    NUM
    - 1;

// Every remaining shape that ends a left-hand operand, so the trailing sign is
// binary and must be flagged: a short-array literal's `]`, a postfix `++`/`--`,
// a backtick shell execution, and the value-producing braces (`match`, an
// anonymous class, a closure) — none of which a control-structure brace shares.
$union = [1, 2, 3]
    + [4, 5, 6];

$postIncrement = $counter++
    + $step;

$postDecrement = $counter--
    - $step;

$executed = `printf 41`
    + 1;

$matched = match ($mode) {
    default => 41,
}
    + 1;

$anonymous = new class () {
}
    + 1;

$closure = function () {
}
    + 1;

$dynamicProperty = $object->{$name}
    + 1;

// Wrapped inside a call-argument list and an array literal: the fixer must lead
// the continuation line one level past the statement's *root* line, never one
// level deeper because the operator sits inside a bracket.
$called = someCall(
    $value
    + $four
);

$array = [
    $base
    - $discount,
];

// A bracketed operator nested inside an indented block anchors on the block's
// statement line, so its continuation indent is one level past that — not past
// the enclosing parenthesis.
function check(int $left, int $right): int
{
    return between(
        $left
        + $right
    );
}

// Inside a *multi*-condition — one already carrying a top-level boolean —
// CleanCode.Conditionals.OneConditionPerLine polices only the boolean's
// placement, so a trailing math operator is this sniff's to report.
if (
    $isActive
    && $charlie
    + $alpha > $bravo
) {
    //
}

// A comment between the operands would be reordered by the fix, so this one is
// reported but deliberately left unfixed.
$commented = 4 + // trailing note
    4;
