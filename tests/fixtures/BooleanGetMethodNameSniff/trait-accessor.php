<?php

declare(strict_types=1);

namespace App\Models\Concerns;

// Violation: a trait is not a model, even under a Models namespace segment.
// CleanCode.Naming.ModelNamingConventions reads classes only, and this sniff
// must agree with it.
trait HasVisibility
{
    public function getIsVisibleAttribute(): bool
    {
        return true;
    }
}
