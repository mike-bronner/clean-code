<?php

/**
 * Two small types for exercising the `minimum` property. Under the default of
 * 45 both are silent; the tests lower the threshold to reach them.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Fixtures\ExcessivePublicCount;

/** 3 public members: one property and two methods. */
class ThreePublicMembers
{
    public int $first = 1;

    public function second(): void
    {
    }

    public function third(): void
    {
    }
}

/** 2 public members, plus two non-public ones the count must ignore. */
class TwoPublicMembers
{
    public int $first = 1;

    protected int $hidden = 2;

    public function second(): void
    {
    }

    private function alsoHidden(): void
    {
    }
}
