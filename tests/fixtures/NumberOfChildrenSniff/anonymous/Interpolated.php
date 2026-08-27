<?php

declare(strict_types=1);

namespace Fixture\Anonymous;

/**
 * The same shape with an interpolated string in the constructor-argument list.
 *
 * The third construct that balances braces inside an argument list, and the one
 * that reaches the depth through the tokenizer's own asymmetry rather than
 * through two bare braces: `{$value}` opens with an array token and closes with
 * a bare `}`. Its own file for the same reason as Matched.php.
 */

trait Specter
{
}

class InterpolatedAnchor
{
}

class InterpolatedAnchorChild extends InterpolatedAnchor
{
}

function interpolatedArgument(string $value): object
{
    return new class ("value: {$value}") extends InterpolatedAnchor {
        use Specter;
    };
}

class ChildOfSpecter extends Specter
{
}
