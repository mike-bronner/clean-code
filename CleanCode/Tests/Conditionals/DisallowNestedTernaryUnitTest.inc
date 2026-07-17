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
