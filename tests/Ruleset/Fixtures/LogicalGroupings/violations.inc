<?php

declare(strict_types=1);

namespace App\Fixtures;

class Violations
{
    public function unindentedGroup(): void
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

    public function tooShallowGroup(): void
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

    public function tooDeepGroup(): void
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

    public function misalignedCondition(): void
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

    public function nestedInnerGroupUnindented(): void
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
