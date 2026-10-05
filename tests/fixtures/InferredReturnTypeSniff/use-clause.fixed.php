<?php

declare(strict_types=1);

// Closures with a `use` clause. A closure's return type follows the use list,
// so detection and fix placement both anchor on the use list's closer, never on
// the parameter list's.

// NOT reported: the type is declared after the use clause.
$typed = function (int $severity, string $message) use (&$reason): bool {
    $reason = $message;

    return true;
};

// Violation, fixable: bool, written after the use clause.
$untyped = function (int $severity, string $message) use (&$reason): bool {
    if ($severity > 0) {
        return true;
    }

    return false;
};

// Violation, fixable: int, written after the whole use list, not its first item.
$captures = function () use ($first, &$second): int {
    return 5;
};

// NOT reported: comments between every pair of signature tokens.
$commentedTyped = function () /* params */ use ($first) /* uses */ : int {
    return 5;
};

// Violation, fixable: int, written straight after the use list's closer.
$commented = function () /* params */ use ($first): int /* uses */ {
    return 5;
};
