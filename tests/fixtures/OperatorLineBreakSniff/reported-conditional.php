<?php

declare(strict_types=1);

// Dangling comparison inside a multi-condition: "||" is the top-level boolean,
// so OneConditionPerLine polices only that operator's placement. The "==="
// would otherwise slip through both sniffs — this one must flag it.
if (
    $a ===
    $b
    || $ready
) {
    $first = 1;
}

// Dangling concatenation inside a multi-condition ("&&" is the top-level
// boolean); the "." is not owned by OneConditionPerLine, so it is flagged here.
while ($alpha
    && $beta .
    $gamma
) {
    break;
}

// Dangling comparison inside an inner grouping parenthesis: its innermost
// enclosing paren is the "(", not the if, so it is this sniff's to report
// regardless of the outer condition.
if (
    ($delta ===
    $epsilon)
    || $zeta
) {
    $second = 1;
}

// A for-loop's init and increment clauses share the condition's parentheses, but
// OneConditionPerLine confines every check it makes to the clause between the
// two semicolons — so a dangling boolean in either of the other two is this
// sniff's to report. Deferring it there would drop the violation outright, the
// sniff deferred to never walking that far.
for (
    $ready = $isActive &&
    $isVerified;
    $index < $limit;
    $ready = $ready ||
    $isRetryable
) {
    echo $ready;
}
