<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * An anonymous class inside another anonymous class's constructor-argument
 * list, each with a trait `use` in its own body.
 *
 * Two declarations are then waiting for a body at once, and the inner one — a
 * parenthesis deeper — has to be served first. A tracker that kept only the
 * most recent declaration, or that matched a body brace to any waiting
 * declaration rather than to the one at its own parenthesis depth, gives the
 * inner class's body to the outer one and leaks both trait names into the
 * import map.
 *
 * Both decoys in Bare.php therefore have to stay silent, not just one.
 */

trait Shade
{
}

trait Banshee
{
}

class NestedInnerAnchor
{
}

class NestedInnerAnchorChild extends NestedInnerAnchor
{
}

class NestedOuterAnchor
{
}

class NestedOuterAnchorChild extends NestedOuterAnchor
{
}

function nestedArgument(): object
{
    return new class (new class (function (): int {
        return 1;
    }) extends NestedInnerAnchor {
        use Banshee;
    }) extends NestedOuterAnchor {
        use Shade;
    };
}

class ChildOfShade extends Shade
{
}

class ChildOfBanshee extends Banshee
{
}
