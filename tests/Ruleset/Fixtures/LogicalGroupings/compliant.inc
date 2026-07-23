<?php

declare(strict_types=1);

namespace App\Fixtures;

class Compliant
{
    public function singleLevelGroup(): void
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

    public function singleLineGroupIgnored(): void
    {
        if (
            ($this->isActive || $this->isTrial)
            && $this->hasLicense
        ) {
            $this->grant();
        }
    }

    public function simpleMultiLineIgnored(): void
    {
        if (
            $this->isActive
            && $this->hasLicense
            || $this->isAdmin
        ) {
            $this->grant();
        }
    }

    public function callArgumentIgnored(): void
    {
        if (
            $this->matches($this->isActive && $this->hasLicense)
            || $this->isAdmin
        ) {
            $this->grant();
        }
    }

    public function multiLineCallArgumentIgnored(): void
    {
        // The call's argument list is not a condition grouping, so its own
        // indentation is out of scope here even when it wraps and carries a
        // boolean — the "  " arg indent below would be flagged if it were.
        if (
            $this->matches(
              $this->isActive && $this->hasLicense
            )
            || $this->isAdmin
        ) {
            $this->grant();
        }
    }

    public function anonClassCtorArgumentIgnored(): void
    {
        // A `new class(...)` constructor argument list is a call, not a
        // condition grouping. Even when it wraps across lines and carries a
        // boolean, the args are out of scope — no false positives — so the
        // "  " arg indent below must not be flagged as a misindented group.
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
