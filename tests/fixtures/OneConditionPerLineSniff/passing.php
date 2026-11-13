<?php

// Positive: single condition on one line with its keyword.
if ($isActive) {
    doSomething();
}

// Positive: single condition carrying nested function-call parentheses.
if (in_array($value, $allowed, true)) {
    doSomething();
}

// Positive: multi-condition, each condition on its own line, operators leading.
if (
    $isActive
    && $hasLicense
    || $isAdmin
) {
    doSomething();
}

// Positive: first condition on the keyword line, operator leading.
if ($isActive
    && $hasLicense
) {
    doSomething();
}

// Positive: a parenthesized group counts as a single condition part.
if (
    ($isActive || $isTrial)
    && $hasLicense
) {
    doSomething();
}

// Positive: boolean operators inside nested call parentheses are not top-level.
if (evaluate($a && $b)) {
    doSomething();
}

// Positive: single-condition while on one line.
while ($queue->isNotEmpty()) {
    $queue->pop();
}

// Positive: multi-condition while, operators leading.
while (
    $queue->isNotEmpty()
    && $attempts < $limit
) {
    $queue->pop();
}

// Positive: elseif chains follow the same rule as if.
if ($isActive) {
    doSomething();
} elseif (
    $hasLicense
    && $isAdmin
) {
    doOtherThing();
} else {
    doSomethingElse();
}

// Positive: a for statement's three clauses are not boolean conditions.
for ($index = 0; $index < $count; $index++) {
    handle($index);
}

// Positive: a multi-condition assignment with each condition on its own line, operators leading.
$isActiveOrPastDue = $subscription?->active()
    || $subscription?->pastDue();

// Positive: a multi-condition return with each condition on its own line, operators leading.
function isReaderEnabled(): bool
{
    return $this->isEnabled
        && BaseFeature::active("bible-reader-tools");
}

// Positive: a multi-condition arrow-function body with operators leading.
$visible = array_filter($items, fn ($item) => $item->isPublished
    && $item->isListed);

// Positive: a multi-condition match-arm body with operators leading.
$state = match (true) {
    $isTrial => $hasCard
        && $isVerified,
    default => false,
};

// Positive: a single-condition assignment, return, arrow function and match arm.
$isActive = $subscription?->active();
$isPaid = fn ($invoice) => $invoice->isPaid();
$tier = match ($plan) {
    'pro' => $isAnnual,
    default => null,
};

// Positive: a single-condition expression may span lines outside a control structure.
$isAllowed = in_array(
    $value,
    $allowed,
    true
);

// Positive: boolean operators inside parentheses, call arguments or arrays are not top-level.
$grouped = ($isActive || $isTrial);
$evaluated = evaluate($isActive && $hasLicense);
$flags = [$isActive && $hasLicense, $isAdmin];

// Positive: a closure body is its own scope, not part of the assignment.
$check = function () use ($isActive): bool {
    return $isActive;
};

// Positive: an array key's value is not an assignment.
$options = ['enabled' => $isActive && $hasLicense];

// Positive: a multi-condition ternary with the condition, "?" and ":" each leading a line.
$limit = $isActive
    && $hasLicense
    ? $high
    : $low;

// Positive: an expression nested anywhere inside a control-structure condition belongs to that condition.
if (match (true) { $isTrial => $hasCard && $isVerified, default => false }) {
    doSomething();
}

if ([$key = $isActive && $hasLicense][0]) {
    doSomething();
}

if (array_any($items, fn ($item) => $item->isPaid && $item->isListed)) {
    doSomething();
}

// Positive: each ternary branch condition sits one level past its "?" or ":" line.
$handler = $isEnabled
    ? $isPaid
        && $isListed
    : $isDraft
        || $isPending;
$fallback = $isCurrent
    ?: $isPaid
        && $isListed;

// Positive: an assignment ends at "and", "or" and "xor", which bind looser than "=".
$allowed = ($isActive and $hasLicense) and $isAdmin;
$granted = $isOwner or $isAdmin;
$exclusive = $isMonthly xor $isAnnual;
