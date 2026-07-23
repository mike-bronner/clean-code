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
}
