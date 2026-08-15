<?php

declare(strict_types=1);

// Inline usage — both operands on one line — is always compliant, including
// operators acting as call parameters.
$sum = 4 + 4;
$result = floor(4 + 4.1);
$product = $first * $second - $third;
$mask = $high << $shift | $low;

// Multi-line statements: every manipulation operator starts the new line.
$result = 4
    + 4;

$math = $base
    - $discount
    * $quantity;

// The remaining math operators wrap the same way.
$scaled = $total
    / $count
    % $limit
    ** $power;

$bits = $high
    << $shift
    | $low
    ^ $toggle
    & $keep;

$shifted = $high
    >> $shift;

// An operator alone on its own line still leads that line — compliant.
$spread = $first
    +
    $second;

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

// The `&` sibling of that clause. PHP itself rejects an intersection type in a
// catch, but PHP_CodeSniffer only tokenises — and it leaves this `&` a plain
// T_BITWISE_AND exactly as it leaves the `|` above a T_BITWISE_OR — so the
// exemption has a second live branch, and this is what pins it.
try {
    //
} catch (RuntimeException &
    LogicException $error) {
    //
}

// Unary sign and reference forms carry no left-hand operand, so they are not
// manipulation operators even when a newline follows them.
$negative = -5;
$positive = +5;
$notted = ~$bits;

$value = -
    5;

$reference = &
    $original;

// A binary operator that already leads is compliant even next to a unary sign.
$total = $base
    + -$adjustment;

// Near-miss: a control structure's closing brace ends a *statement*, not a
// value, so the sign that follows it opens a new (discarded) statement and is
// unary — however it wraps. Only the value-producing braces of failing.php
// (`match`, an anonymous class, a closure, and the four dereference forms)
// continue an expression. One case per scope owner, since the brace token is
// identical in all of them and only the scope it closes tells them apart.
if ($isActive) {
    //
} -
    5;

while ($isVerified) {
    //
} +
    5;

foreach ($items as $item) {
    //
} -
    5;

for ($index = 0; $index < 3; $index++) {
    //
} +
    5;

switch ($mode) {
    default:
        break;
} -
    5;

try {
    //
} finally {
    //
} +
    5;

function scopeClosingBrace(): void
{
    //
} -
    5;

// Near-miss: a bare compound-statement block carries no scope owner at all, the
// same as the dynamic fetches of failing.php — so the absent owner cannot be
// read as "this brace closes a value". Its `}` ends a statement, so the sign
// after it opens a new (discarded) one and is unary. Each position a bare block
// can take, since the brace is identical in all of them and only the token that
// opened it separates a block from a fetch.
{
    //
} -
    5;

{
    //
}
{
    //
} +
    5;

if ($isActive) {
    {
        //
    } -
        5;
}

barelyLabelled:
{
    //
} -
    5;

// A reference `&` is recognised by context, not by the preceding token, so a
// by-reference parameter after a type name — itself an operand terminator —
// and a by-reference `use (` capture are both left alone.
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

// Near-miss: the remaining manipulation operators of the standard's list are
// owned by CleanCode.Operators.OperatorLineBreak (#35), so this sniff stays
// silent on them even though they trail here. Registering them here as well
// would report each of these wraps twice.
$string = "Hello" .
    strtolower(", world!");

$flags = $isActive &&
    $isVerified ||
    $isAdmin;

// Near-miss: inside a *single* condition — one carrying no top-level boolean —
// CleanCode.Conditionals.OneConditionPerLine reports the wrap and collapses it
// onto one line, so this sniff defers there.
if (
    $alpha +
    $bravo > $charlie
) {
    //
}

while (
    $alpha |
    $bravo
) {
    //
}

// The other side of that boundary in a for-loop header. Only the clause between
// the semicolons is read for the top-level boolean that classifies the
// condition, so the `&&` in the init below does not reach it: the condition is
// single, OneConditionPerLine collapses it, and this sniff defers. Reading the
// whole header instead would call it multi and report the wrap twice.
for (
    $ready = $isActive && $isVerified;
    $alpha +
    $bravo > $charlie;
    $index++
) {
    //
}
