<?php

// Compliant: single-level ternary assignment.
$status = $isActive ? 'active' : 'inactive';

// Compliant: short ternary.
$label = $name ?: 'anonymous';

// Compliant: ternary in a return.
function describeCount(int $count): string
{
    return $count > 0 ? 'some' : 'none';
}

// Compliant: multi-line ternary.
$message = $isEnabled
    ? 'feature enabled'
    : 'feature disabled';

// Compliant: ternary as a parameter default value.
function renderMode(string $mode = PHP_SAPI === 'cli' ? 'console' : 'web'): string
{
    return $mode;
}

// Compliant: sibling ternaries in separate arguments of one call.
$larger = max($first > 0 ? $first : 0, $second > 0 ? $second : 0);

// Compliant: sibling ternaries in separate elements of one array literal.
$flags = [
    'first' => $first > 0 ? 'yes' : 'no',
    'second' => $second > 0 ? 'yes' : 'no',
];

// Compliant: grouped ternaries combined by a non-ternary operator.
$total = ($first > 0 ? 1 : 0) + ($second > 0 ? 1 : 0);

// Compliant: a ternary inside a call argument is not direct nesting, even
// when the call itself sits in a ternary branch.
$value = $isRaw ? trim($input ?: 'n/a') : 'none';

// Violation: ternary nested in the then-branch.
$price = $isMember ? ($hasCoupon ? 5 : 8) : 10;

// Violation: ternary nested in the else-branch.
$price = $isMember ? 5 : ($hasCoupon ? 8 : 10);

// Violation: ternary nested in the condition.
$mode = ($flag ? $high : $low) ? 'on' : 'off';

// Violation: chained short ternaries.
$name = $nickname ?: $fullName ?: 'anonymous';

// Violation: nested multi-line ternary.
$tier = $isPremium
    ? ($isAnnual ? 'premium-annual' : 'premium-monthly')
    : 'basic';

// Compliant: sibling ternaries across a match arm — the arm's condition and
// value are separate segments split by the arrow.
$rate = match (true) {
    $flag ? 1 : 2 => $other ? 3 : 4,
    default => 5,
};

// Compliant: a ternary inside an arrow-function body is bounded by the
// arrow, even when the arrow function sits in another ternary's branch.
$callback = $isLazy ? (fn ($x) => $x ? 1 : 2) : null;

// Violation: ternary nested inside a match arm's value.
$fee = match ($plan) {
    'pro' => $isAnnual ? ($hasCoupon ? 90 : 100) : 120,
    default => 0,
};

// Violation: ternary nested inside an arrow-function body.
$resolver = fn ($x) => $x > 0 ? ($x > 10 ? 'big' : 'small') : 'none';
