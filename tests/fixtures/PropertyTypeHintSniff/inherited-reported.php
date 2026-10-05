<?php

declare(strict_types=1);

namespace App\Nova;

use MikeBronner\CleanCode\Tests\Ancestors\AppResource;
use MikeBronner\CleanCode\Tests\Ancestors\BrokenParent;
use MikeBronner\CleanCode\Tests\Ancestors\PrivateHolder;
use MikeBronner\CleanCode\Tests\Ancestors\TypedParent;

// Violation: no ancestor declares it.
class Standalone
{
    public $loose = 1;
}

// Violation: no ancestor declares $model. VendorResource only reads
// static::$model, so a native string type is legal here.
class Book extends AppResource
{
    public static $model = 'App\Models\Book';
}

// Violation: the nearest declaration is private, so this is a new property.
class RevealsSecret extends PrivateHolder
{
    protected $secret;
}

// Violation: the nearest declaration, TypedParent::$count, has a native type.
// UntypedGrandparent declares $count untyped further up, but privately.
class Counter extends TypedParent
{
    protected $count = 1;
}

// Violation: the parent cannot be resolved.
class Orphan extends \Does\Not\Exist
{
    public $value;
}

// Violation: loading the parent throws, because its own parent is missing.
class OnBrokenParent extends BrokenParent
{
    public $value;
}
