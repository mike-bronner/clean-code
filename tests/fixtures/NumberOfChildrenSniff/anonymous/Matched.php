<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * The same shape with a `match` expression in the constructor-argument list.
 *
 * `match` is the second construct that writes a balanced pair of bare braces
 * inside an argument list, so it drives the same premature close as the closure
 * in Closure.php by the same route. It is a separate file so that a fix which
 * only handles one of the two constructs is still caught.
 */

trait Phantom
{
}

class MatchedAnchor
{
}

class MatchedAnchorChild extends MatchedAnchor
{
}

function matchArgument(): object
{
    return new class (match (true) {
        default => 1,
    }) extends MatchedAnchor {
        use Phantom;
    };
}

class ChildOfPhantom extends Phantom
{
}
