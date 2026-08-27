<?php

declare(strict_types=1);

namespace Fixture\ReadonlyClasses;

/**
 * A named class carries `readonly` in front of the `class` keyword too, and
 * that is the shape a fix for the anonymous one can break: reading `readonly`
 * as the mark of an anonymous class drops every child below and the count
 * falls to nothing.
 *
 * Fifteen children of a readonly parent, which is the shipped threshold
 * exactly and so a violation. A readonly class can only be extended by another
 * readonly class, so the whole hierarchy carries the modifier.
 */
abstract readonly class Base
{
}

readonly class Child1 extends Base
{
}

readonly class Child2 extends Base
{
}

readonly class Child3 extends Base
{
}

readonly class Child4 extends Base
{
}

readonly class Child5 extends Base
{
}

readonly class Child6 extends Base
{
}

readonly class Child7 extends Base
{
}

readonly class Child8 extends Base
{
}

readonly class Child9 extends Base
{
}

readonly class Child10 extends Base
{
}

readonly class Child11 extends Base
{
}

readonly class Child12 extends Base
{
}

readonly class Child13 extends Base
{
}

readonly class Child14 extends Base
{
}

readonly class Child15 extends Base
{
}
