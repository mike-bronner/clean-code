<?php

// An if/else whose branches only assign the same variable → use a ternary.
if ($isActive) {
    $status = 'active';
} else {
    $status = 'inactive';
}

// An if/else whose branches only return → use a ternary.
function describeCount(int $count): string
{
    if ($count > 0) {
        return 'some';
    } else {
        return 'none';
    }
}

// A ternary nested inside a ternary branch.
$price = $isMember ? ($hasCoupon ? 5 : 8) : 10;

// Chained short ternaries nest as well.
$name = $nickname ?: $fullName ?: 'anonymous';

// Redundant grouping parentheses around a chain flag only the genuinely
// nested second operator — never the chain head.
$alias = ($nickname ?: $fullName ?: 'anonymous');

// The same grouped chain combined by a non-ternary operator.
$count = ($primary ?: $fallback ?: 0) + $offset;
