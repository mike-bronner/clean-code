<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * The `new readonly class (…)` spelling of the same shape.
 *
 * `readonly` sits between `new` and `class`, so this declaration reaches the
 * body tracking by a different route from the plain spelling — it is not
 * recognised as anonymous at the keyword at all, and is dropped a step later for
 * declaring no name. The body still has to be tracked either way, and the
 * closure in the argument list still closes it early when it is recorded at the
 * keyword.
 *
 * PDepend 2.16.2 cannot parse this spelling, so PHPMD has no verdict on it to
 * match; what is pinned here is that the sniff keeps its own bearings through
 * it.
 */

trait Wraith
{
}

class ReadonlyAnchor
{
}

class ReadonlyAnchorChild extends ReadonlyAnchor
{
}

function readonlyArgument(): object
{
    return new readonly class (function (): int {
        return 1;
    }) extends ReadonlyAnchor {
        use Wraith;
    };
}

class ChildOfWraith extends Wraith
{
}
