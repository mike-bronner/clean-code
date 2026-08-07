<?php

declare(strict_types=1);

namespace Vendor\Package;

abstract class CompliantAbstractExample
{
    abstract protected function transform(string $input): string;

    final public function apply(string $input): string
    {
        return trim($this->transform($input));
    }
}
