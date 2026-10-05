<?php

declare(strict_types=1);

namespace App\Models;

// Not a violation: a class under a Models namespace segment is a model, even
// when it extends an app-owned base model rather than Eloquent's.
class Post extends BaseModel
{
    public function getIsPublishedAttribute(): bool
    {
        return true;
    }
}
