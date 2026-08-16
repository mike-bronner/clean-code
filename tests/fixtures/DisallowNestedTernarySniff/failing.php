<?php

// Ternary nested in the then-branch.
$price = $isMember ? ($hasCoupon ? 5 : 8) : 10;

// Ternary nested in the else-branch.
$fee = $isMember ? 5 : ($hasCoupon ? 8 : 10);

// Ternary nested in the condition.
$mode = ($flag ? $high : $low) ? 'on' : 'off';

// Chained short ternaries.
$name = $nickname ?: $fullName ?: 'anonymous';

// Nested multi-line ternary.
$tier = $isPremium
    ? ($isAnnual ? 'premium-annual' : 'premium-monthly')
    : 'basic';

// Redundant grouping parentheses around a chained short ternary still produce
// exactly one error, at the genuinely nested second operator — the chain head
// is not flagged.
$alias = ($nickname ?: $fullName ?: 'anonymous');

// The same grouped chain combined by a non-ternary operator.
$count = ($primary ?: $fallback ?: 0) + $offset;

// A grouped ternary feeding another ternary's condition is genuine condition
// nesting, whether the group is compared, invoked, or added to.
$compared = ($a ? 1 : 2) > 0 ? 'x' : 'y';
$invoked = ($a ? $f : $g)($y) ? 'x' : 'y';
$summed = ($first > 0 ? 1 : 0) + $second ? 'x' : 'y';

// Ternary nested inside a match arm's value.
$plan = match ($tierName) {
    'pro' => $isAnnual ? ($hasCoupon ? 90 : 100) : 120,
    default => 0,
};

// Ternary nested inside an arrow-function body — the body bounds the segment,
// it does not exempt what is nested within it.
$resolver = fn ($x) => $x > 0 ? ($x > 10 ? 'big' : 'small') : 'none';

// The same nesting inside an immediately-invoked arrow-function body, whose
// result is another ternary's condition. Only the body's inner operator is
// reported: the outer ternary does not nest the body, and the body's own
// outer operator is not nested by anything.
$deep = (fn ($x) => $x ? ($y ? 1 : 2) : 3)($z) ? 'a' : 'b';

// An invoked arrow function in a ternary's condition, where that ternary is
// itself the condition of another — nesting the grouped result reports once,
// at the inner operator.
$layered = ((fn () => 1)() ? 'a' : 'b') ? 'x' : 'y';
