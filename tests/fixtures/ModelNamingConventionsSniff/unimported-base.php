<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A class extending a bare `Model` with no import of that name anywhere in the
 * file. PHP resolves it against the enclosing namespace, so this is
 * App\Support\Model — some local base of this project's own, not Eloquent's.
 *
 * Outside `Illuminate\Database\Eloquent` itself an unimported `Model` can never
 * be the Eloquent one: reaching that class requires a `use` statement or a
 * leading backslash. So nothing here is a model, and none of the naming rules
 * apply — the boolean property below stays unreported.
 */
class Money extends Model
{
    public bool $rounded = false;

    public function negative(): bool
    {
        return false;
    }
}
