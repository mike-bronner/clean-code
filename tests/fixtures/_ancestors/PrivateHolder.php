<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ancestors;

class PrivateHolder
{
    private static $registry = [];

    private $secret;

    public function registry()
    {
        return static::$registry;
    }
}
