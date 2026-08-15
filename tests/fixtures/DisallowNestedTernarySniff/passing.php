<?php

// Single-level ternary assignment.
$status = $isActive ? 'active' : 'inactive';

// Short ternary.
$label = $name ?: 'anonymous';

// Ternary in a return.
function describeCount(int $count): string
{
    return $count > 0 ? 'some' : 'none';
}

// Multi-line ternary.
$message = $isEnabled
    ? 'feature enabled'
    : 'feature disabled';

// Ternary as a parameter default value.
function renderMode(string $mode = PHP_SAPI === 'cli' ? 'console' : 'web'): string
{
    return $mode;
}

// Sibling ternaries in separate arguments of one call.
$larger = max($first > 0 ? $first : 0, $second > 0 ? $second : 0);

// Sibling ternaries in separate elements of one array literal.
$flags = [
    'first' => $first > 0 ? 'yes' : 'no',
    'second' => $second > 0 ? 'yes' : 'no',
];

// Grouped ternaries combined by a non-ternary operator.
$total = ($first > 0 ? 1 : 0) + ($second > 0 ? 1 : 0);

// A ternary inside a call argument is not direct nesting, even when the call
// itself sits in a ternary branch.
$value = $isRaw ? trim($input ?: 'n/a') : 'none';

// A ternary inside an array element is bounded by the array, even when the
// array literal sits in a ternary branch.
$sizes = $isRaw ? [$width ?: $default] : [];

// Sibling ternaries across a match arm — the arm's condition and value are
// separate segments split by the arrow.
$rate = match (true) {
    $flag ? 1 : 2 => $other ? 3 : 4,
    default => 5,
};

// A ternary inside an arrow-function body is bounded by the arrow, even when
// the arrow function sits in another ternary's branch.
$callback = $isLazy ? (fn ($x) => $x ? 1 : 2) : null;

// The same body, with the arrow function immediately invoked and its result
// used as another ternary's condition. The body ternary is still bounded: the
// wrapping parenthesis closes the body, it does not group the two together.
$flagged = (fn ($x) => $x ? 1 : 2)($y) ? 'a' : 'b';

// The invoked form with a short ternary body, and with a short ternary
// outside it — the boundary is the body, not the operator spelling.
$short = (fn ($x) => $x ?: 2)($y) ? 'a' : 'b';
$either = (fn ($x) => $x ? 1 : 2)($y) ?: 'b';

// Redundant parentheses around the arrow function, and a static arrow
// function, close the body just the same.
$doubled = ((fn ($x) => $x ? 1 : 2))($y) ? 'a' : 'b';
$strict = (static fn ($x) => $x ? 1 : 2)($y) ? 'a' : 'b';

// Two invoked arrow functions in one condition: neither body is nested by the
// ternary that consumes them, nor by each other.
$combined = (fn ($x) => $x ? 1 : 2)($y) + (fn ($z) => $z ? 3 : 4)($w) ? 'a' : 'b';

// A body ternary preceded by a sibling arrow function that has already
// closed — the enclosing body is still what bounds it.
$sibling = (fn ($a) => (fn () => 1)() + ($x ? 1 : 2))($z) ? 'p' : 'q';

// A grouped body expression, invoked: the grouping parenthesis sits inside
// the body, so unwrapping it never reaches past the body's end.
$grouped = (fn ($a) => ($x ? 1 : 2))($z) ? 'p' : 'q';

// A ternary in a call argument whose call sits in another ternary's
// condition — the call parenthesis bounds it.
$called = ($obj->m($x ? 1 : 2)) ? 'a' : 'b';

// A ternary in an array element whose array is subscripted into another
// ternary's condition — the bracket bounds it.
$indexed = [$x ? 1 : 2][0] ? 'a' : 'b';

// A ternary spanning a heredoc branch: the heredoc body is tokenized one
// token per physical line, and none of them is a ternary operator.
$text = $x ? <<<TXT
    is it? maybe
    TXT : $y;

// A "?" and a ":" inside string literals are not ternary operators.
$quoted = $x ? 'a ? b : c' : $y;
