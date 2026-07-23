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

    public function arrowOperandUntouched(): void
    {
        // phpcbf must leave an arrow-function body alone: it is a single
        // operand, not a group, so its wrapped body lines appear identically
        // in the fixed output — reindenting them would corrupt valid code.
        if (
            $this->isAdmin
            || (
            fn (): bool => $this->isActive
            && $this->hasLicense
            )()
        ) {
            $this->grant();
        }
    }

    public function commentInGroupUntouched(): void
    {
        // phpcbf must leave a comment line inside a grouping alone: it is not a
        // condition, so the deliberately misindented comment appears
        // identically in the fixed output.
        if (
            $this->isAdmin
            || (
            // deliberately misindented comment — not a condition
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }
}
