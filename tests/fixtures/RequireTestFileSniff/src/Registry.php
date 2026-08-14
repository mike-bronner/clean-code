<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * A concrete class with its companion test, holding an anonymous class in a
 * method body. The anonymous class is T_ANON_CLASS and has no name a test
 * could be named after; register it and this file reports a violation with no
 * file name to put in the message.
 */
class Registry
{
    public function make(): object
    {
        return new class () {
            public function value(): int
            {
                return 6;
            }
        };
    }
}
