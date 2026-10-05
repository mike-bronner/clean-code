<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ancestors;

class TypedParent extends UntypedGrandparent
{
    protected int $count = 0;

    public static string $label = '';
}
