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

    public function arrowOperandIgnored(): void
    {
        // An arrow function used as a boolean operand is a single condition,
        // not a parenthesized grouping: its body carries a boolean but has no
        // bracket delimiter, so the wrapped body lines below (at the enclosing
        // level, not one deeper) must not be flagged as a misindented group.
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

    public function commentInGroupIgnored(): void
    {
        // A comment line inside a grouping is not a condition. The real
        // conditions below sit at the correct one-deeper level; the
        // deliberately misindented comment must not be flagged as the group's
        // first condition, nor shift which condition is measured against it.
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
