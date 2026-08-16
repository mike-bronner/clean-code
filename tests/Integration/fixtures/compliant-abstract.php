<?php

declare(strict_types=1);

namespace Vendor\Package;

abstract class CompliantAbstractExample
{
    protected string $prefix = '';

    abstract protected function transform(string $input): string;

    final public function apply(string $input): string
    {
        return trim($this->prefix . $this->transform($input));
    }
}
