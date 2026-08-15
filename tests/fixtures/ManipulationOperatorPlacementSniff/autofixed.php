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

// A dereference brace also carries no scope owner, so what opened it is what
// tells it from the bare block of passing.php. One case per introducing token:
// `->`, `?->`, `$`, and `::`.
$dynamicProperty = $object->{$name}
    + 1;

$nullsafeProperty = $object?->{$name}
    + 1;

$variableVariable = ${$name}
    + 1;

$dynamicStaticCall = Thing::{$name}()
    + 1;

$dynamicStaticProperty = Thing::${$name}
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

// The anchor escapes every token that divides an expression, not only the
// bracket openers: a named argument's `:` and an array key's `=>` each leave
// their operand on an already-indented line. A literal holding one keyed and one
// unkeyed element is the discriminator — two structurally identical wraps that
// land on different indents are the stair-stepping the anchor exists to prevent.
$named = someCall(
    name: $value
    + $four,
);

$keyed = [
    'timeout' => $base
    + $padding,
];

$mixed = [
    $base
    + $one,
    'key' => $base
    + $two,
];

// The same case from underneath: findStartOfStatement() answers a nested call's
// inner `(` with that `(` itself, so without escaping past an anchor that *is* a
// grouping opener the inner call's line would anchor the indent while the
// equivalent array literal escapes to the statement root.
$nestedCall = outer(
    inner(
        $base
    * $factor,
    ),
);

$nestedArray = [
    'outer' => [
        'inner' => $base
    * $factor,
    ],
];

// The boundary side of that classification. A `match` arm and a `switch` case
// body are statements inside a brace block, so each anchors on its own line —
// one level past that, never past whatever encloses the block.
$armed = match ($mode) {
    default => $base
        & $mask,
};

switch ($mode) {
    case 1:
        $cased = $base
            << $shift;
        break;
}

// The value half of the operand model. Unlike the string and bracket families
// there is no PHP_CodeSniffer enumeration to pin these against, so each one
// ends a left-hand operand here instead: a constant name, a bare float, `true`,
// `false`, `null`, a single-quoted string and an array index's `]`. Every case
// trails a `+` or a `-`, the only two operators whose reading the operand model
// gates — `&` is settled by File::isReference() before the model is consulted,
// so a case behind one would pin nothing.
$constant = MAX_RETRIES
    - 1;

$float = 4.5
    + 0.5;

$trueSum = true
    + 1;

$falseSum = false
    - 1;

$nullish = null
    + 1;

$numericString = '41'
    + 1;

$indexed = $values['count']
    - 1;

// A for-loop's init and increment clauses share the condition's parentheses but
// not its ownership: CleanCode.Conditionals.OneConditionPerLine confines every
// check it makes to the clause between the two semicolons, so a wrap in either
// of the other two is this sniff's to report. Deferring it there would drop the
// violation outright, the sniff deferred to never walking that far.
for (
    $index = 0
    + $offset;
    $index < $limit;
    $index = $index
    + $step
) {
    echo $index;
}

// The same two clauses with a boolean in the condition, which classifies it as
// a *multi*-condition and sends the deferral down its other branch. Neither
// clause is deferred on either branch, so the pair holds the boundary whichever
// way the condition reads.
for (
    $cursor = 0
    + $offset;
    $cursor < $limit
    && $cursor > 0;
    $cursor = $cursor
    + $step
) {
    echo $cursor;
}

// A `;` inside parentheses is not automatically a clause divider. A closure
// passed as a call argument carries its whole body — statements and all —
// inside the call's parentheses, so every `;` here reports an enclosing
// parenthesis just as a for header's does. Only the parenthesis a `for` owns
// divides clauses; these terminate statements exactly as a top-level `;` does.
$mapped = array_map(function (int $x): int {
    $doubled = $x * 2;

    return $doubled;
}, $values)
    + $extra;

// A comment between the operands would be reordered by the fix, so this one is
// reported but deliberately left unfixed.
$commented = 4 + // trailing note
    4;
