<?php

namespace Vendor\Package;

class ControlStructuresExample
{
    public function run(int $value): string
    {
        return $value > 0 ? 'positive' : 'other';
    }
}
