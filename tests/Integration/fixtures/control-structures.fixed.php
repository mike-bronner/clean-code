<?php

namespace Vendor\Package;

class ControlStructuresExample
{
    public function run(int $value): string
    {
        if ($value > 0) {
            return 'positive';
        } else {
            return 'other';
        }
    }
}
