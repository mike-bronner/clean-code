<?php

// Compliant: single condition on one line with its keyword.
if ($isActive) {
    doSomething();
}

// Compliant: single condition with nested function-call parentheses.
if (in_array($value, $allowed, true)) {
    doSomething();
}

// Compliant: multi-condition, each condition on its own line, operators leading.
if (
    $isActive
    && $hasLicense
    || $isAdmin
) {
    doSomething();
}

// Compliant: first condition on the keyword line, operator leading.
if ($isActive
    && $hasLicense
) {
    doSomething();
}

// Compliant: parenthesized group counts as a single condition part.
if (
    ($isActive || $isTrial)
    && $hasLicense
) {
    doSomething();
}

// Compliant: boolean operators inside nested call parentheses are not top-level.
if (evaluate($a && $b)) {
    doSomething();
}

// Compliant: single-condition while on one line.
while ($queue->isNotEmpty()) {
    $queue->pop();
}

// Compliant: single-condition for on one line.
for ($i = 0; $i < 10; $i++) {
    doSomething();
}

// Compliant: single-condition do-while on one line.
do {
    doSomething();
} while ($queue->isNotEmpty());

// Compliant: a ternary with a single condition is left as it is.
$label = $count > 1
    ? 'items'
    : 'item';

// Compliant: boolean operator inside a short-array value is not top-level.
if (in_array($value, [$a && $b, $c], true)) {
    doSomething();
}

// Violation: single condition split across lines.
if (
    $isActive
) {
    doSomething();
}

// Violation: single condition with a nested call split across lines.
if (in_array(
    $value,
    $allowed,
    true
)) {
    doSomething();
}

// Violation: multi-condition collapsed onto one line.
if ($isActive && $hasLicense) {
    doSomething();
}

// Violation: multi-condition with trailing operators.
if ($isActive &&
    $hasLicense ||
    $isAdmin
) {
    doSomething();
}

// Violation: single-condition while split across lines.
while (
    $queue->isNotEmpty()
) {
    $queue->pop();
}

// Violation: for-loop condition section split across lines.
for ($i = 0; $i <
    10; $i++) {
    doSomething();
}

// Violation: multi-condition for-loop condition collapsed onto one line.
for ($i = 0; $i < 10 && $ok; $i++) {
    doSomething();
}

// Violation: do-while condition split across lines.
do {
    doSomething();
} while (
    $queue->isNotEmpty()
);

// Violation: multi-condition elseif collapsed onto one line.
if ($isActive) {
    doSomething();
} elseif ($hasLicense && $isAdmin) {
    doSomething();
}

// Violation: split single condition containing a comment is reported but not auto-fixed.
if (
    // still checking
    $isActive
) {
    doSomething();
}

// Violation: multi-condition assignment collapsed onto one line.
$isActiveOrPastDue = $subscription?->active() || $subscription?->pastDue();

// Violation: multi-condition return collapsed onto one line.
function isReaderEnabled(): bool
{
    return $this->isEnabled && BaseFeature::active("bible-reader-tools");
}

// Violation: multi-condition arrow-function body collapsed onto one line.
$visible = array_filter($items, fn ($item) => $item->isPublished && $item->isListed);

// Violation: multi-condition match-arm body collapsed onto one line.
$state = match (true) {
    $isTrial => $hasCard && $isVerified,
    default => false,
};

// Violation: multi-line assignment with two conditions sharing a line.
$canEdit = $isOwner || $isEditor
    || $isAdmin;

// Violation: a keyword operator in an arrow-function body, and a compound assignment.
$fallback = fn () => $isTrial or $isGrace;
$exclusive ??= $isMonthly || $isAnnual;

// Violation: a chained assignment is reported once, by the innermost assignment.
$first = $second = $isActive && $hasLicense;
$isSettled = fn ($invoice) => $invoice->isPaid() && $invoice->isCleared();

// Violation: an assignment inside an if condition is reported once, by the if.
if ($found = $isActive && $hasLicense) {
    doSomething();
}

// Violation: a multi-condition ternary condition collapsed onto one line.
function minimumLength($configured): int
{
    return $configured === false || $configured < 1 ? DEFAULT_MINIMUM : $configured;
}
$score = $isActive && $hasLicense ? 1 : 2;
$fallback = $isTrial || $isGrace ?: $default;

// Violation: the ternary operators trail a multi-line condition.
$limit = $isActive
    && $hasLicense ? $high : $low;

// Violation: the literal and keyword in an arrow-function body.
$allowed = fn () => ($isActive and $hasLicense) and $isAdmin;

// Violation: an arrow function in a ternary branch moves only its own operators.
$handler = $isEnabled ? fn ($item) => $item->isPaid && $item->isListed : null;

// Violation: an assignment in a for initializer sits outside the checked condition.
for ($index = 0, $ready = $isActive && $hasLicense; $index < $count; $index++) {
    doSomething();
}

// Violation: multi-condition ternary branches collapsed onto one line.
$handler = $isEnabled ? $isPaid && $isListed : null;
$status = $isCurrent ? $isPaid && $isListed : $isDraft || $isPending;
$preferred = $isCurrent ?: $isPaid && $isListed;

// Violation: "and", "or" and "xor" bind looser than a ternary, so they stay at statement level.
function hasFallback(): bool
{
    return $isCurrent ? $isPaid : $isDraft or $isPending;
}
function tierFor(): int
{
    return $isTrial or $isGrace ? 1 : 2;
}
function isExclusive(): bool
{
    return $isCurrent ? $isPaid && $isListed : $isDraft xor $isPending && $isOpen;
}
function graceTier(): int
{
    return $isTrial or $isGrace
        ? 1
        : 2;
}
