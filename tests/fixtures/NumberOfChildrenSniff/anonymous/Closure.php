<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * An anonymous class whose constructor-argument list carries a closure.
 *
 * The closure's body is a balanced pair of braces written *before* the
 * anonymous class's own body brace is ever reached. A parse that records the
 * class body at the `class` keyword rather than at the brace that opens it
 * therefore has that record sitting at the depth the closure returns to, so the
 * closure's `}` closes a body that has not opened yet — and the `use Ghost;`
 * three tokens later reads as a namespace import rather than as a trait.
 *
 * The leaked alias binds Ghost to the global class of that name in Bare.php,
 * and ChildOfGhost below is then counted as its child.
 */

trait Ghost
{
}

class ClosureAnchor
{
}

class ClosureAnchorChild extends ClosureAnchor
{
}

function closureArgument(): object
{
    return new class (function (): int {
        return 1;
    }) extends ClosureAnchor {
        use Ghost;
    };
}

class ChildOfGhost extends Ghost
{
}
