<?php

declare(strict_types=1);

namespace App\Nova;

use MikeBronner\CleanCode\Tests\Ancestors\AppResource;
use MikeBronner\CleanCode\Tests\Ancestors\TypedParent;

// The crossbible shape: a Nova 5 resource through an app-owned base resource.
class Book extends AppResource
{
    // Not a violation: VendorResource declares $group, $title and $search
    // static and untyped, two levels up.
    public static $group = 'Bible Reader';

    // Not a violation: VendorResource reads static::$model and never declares
    // it, so every resource must. This is the declaration that reported on
    // crossbible.
    public static $model = 'App\Models\Book';

    public static $title = 'name';

    public static $search = ['name'];

    // Violation: no ancestor declares or reads it.
    public static $ownCache = [];
}

// Not a violation: the existing exemption, for a typed static ancestor.
class Labelled extends TypedParent
{
    public static string $label = 'label';
}
