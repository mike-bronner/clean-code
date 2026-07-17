<?php

namespace Vendor\Package;

class VisibilityExample
{
    const VERSION = '1.0';

    var $legacy;

    function run(): mixed
    {
        return $this->legacy;
    }
}
