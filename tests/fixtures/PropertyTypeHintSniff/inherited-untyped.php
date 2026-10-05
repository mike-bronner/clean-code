<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use MikeBronner\CleanCode\Tests\Ancestors\AppResource;

// Not a violation: VendorResource declares these untyped two levels up,
// through the app-owned AppResource, and PHP refuses a native type here.
class Post extends AppResource
{
    public static $group = 'Content';

    public static $title = 'title';

    public static $search = ['id', 'title'];
}

// Not a violation: Eloquent's Model declares these untyped. No @var docblock.
class Comment extends Model
{
    protected $fillable = ['body'];

    protected $casts = [];

    protected $table = 'comments';
}

// Not a violation: Model declares $table two levels up, through User.
class Member extends User
{
    protected $table = 'members';
}
