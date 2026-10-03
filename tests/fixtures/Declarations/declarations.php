<?php

declare(strict_types=1);

function namedFunction(): void
{
}

interface NamedInterface
{
}

trait NamedTrait
{
}

enum NamedEnum
{
}

final class NamedClass
{
    public function namedMethod(): void
    {
        $closure = function (): void {
        };

        $anonymous = new class {
        };
    }
}
