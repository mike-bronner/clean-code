<?php

declare(strict_types=1);

namespace App\Fixtures;

class AutofixBefore
{
    public function shallowGroup(): void
    {
        if (
            $this->isAdmin
            || (
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function deepAndMisaligned(): void
    {
        if (
            $this->isAdmin
            || (
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function nestedGroups(): void
    {
        if (
            $this->isAdmin
            || (
                $this->isActive
                && (
                    $this->hasLicense
                    || $this->isTrial
                )
            )
        ) {
            $this->grant();
        }
    }

    public function anonClassCtorArgumentUntouched(): void
    {
        // phpcbf must leave a `new class(...)` constructor argument list alone:
        // its boolean args are a call, not a group, so these lines appear
        // identically in the fixed output — corrupting them would fail the
        // round-trip assertion.
        if (
            $this->isAdmin
            || new class(
              $this->isActive && $this->hasLicense
            ) {
            }
        ) {
            $this->grant();
        }
    }
}
