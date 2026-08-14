<?php

namespace Vendor\Package;

class ControlStructuresExample
{
    public function run(int $value): string
    {
        if ($value > 0) {
            return 'positive';
        }
        return 'other';
    }
}
