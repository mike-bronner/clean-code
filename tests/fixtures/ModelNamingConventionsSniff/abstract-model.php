<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Abstract methods have no body to close them, only a semicolon. Their
 * parameters must not be read as model properties, and the walk over the class
 * body has to resume correctly after them.
 */
abstract class BaseModel extends Model
{
    abstract public function hasQuota(bool $strict): bool;

    abstract protected function findUserById(int $id): User;

    public function expired(): bool
    {
        return true;
    }
}
