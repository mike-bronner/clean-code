<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ancestors;

abstract class VendorResource
{
    public static $group = 'Other';

    public static $title = 'id';

    public static $search = [];

    public function newModel()
    {
        $model = static::$model;

        return new $model();
    }
}
