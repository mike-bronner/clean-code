<?php

declare(strict_types=1);

namespace App\Nova;

use MikeBronner\CleanCode\Tests\Ancestors\BrokenParent;
use MikeBronner\CleanCode\Tests\Ancestors\PrivateHolder;

// Violation: the parent cannot be resolved.
class Orphan extends \Does\Not\Exist
{
    public static $model = 'App\Models\Orphan';
}

// Violation: loading the parent throws, because its own parent is missing.
class OnBrokenParent extends BrokenParent
{
    public static $model = 'App\Models\Broken';
}

// Violation: the only ancestor declaration is private, even though that
// ancestor reads static::$registry.
class RevealsRegistry extends PrivateHolder
{
    private static $registry = [];
}
