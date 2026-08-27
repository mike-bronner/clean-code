<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * The `new #[Marker] class (…)` spelling of the same shape — an attribute
 * between `new` and `class`, which is the third way the declaration keyword can
 * be reached and the second that hides `new` from the anonymous check.
 */

#[\Attribute]
class Marker
{
}

trait Revenant
{
}

class AttributedAnchor
{
}

class AttributedAnchorChild extends AttributedAnchor
{
}

function attributedArgument(): object
{
    return new #[Marker] class (function (): int {
        return 1;
    }) extends AttributedAnchor {
        use Revenant;
    };
}

class ChildOfRevenant extends Revenant
{
}
