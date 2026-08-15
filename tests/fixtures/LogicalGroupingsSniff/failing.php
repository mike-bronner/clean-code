<?php

declare(strict_types=1);

namespace App\Fixtures;

class Failing
{
    private const ACTIVE = true;

    private const LICENSED = true;

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

    public function elseifUnindentedGroup(): void
    {
        if ($this->isGuest) {
            $this->deny();
        } elseif (
            $this->isAdmin
            || (
            $this->isActive
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function whileUnindentedGroup(): void
    {
        while (
            $this->isAdmin
            || (
            $this->isActive
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function forUnindentedGroup(): void
    {
        for (
            $i = 0;
            $i < $this->max
            || (
            $this->isActive
            && $this->hasLicense
            );
            $i++
        ) {
            $this->grant();
        }
    }

    public function doWhileUnindentedGroup(): void
    {
        do {
            $this->grant();
        } while (
            $this->isAdmin
            || (
            $this->isActive
            && $this->hasLicense
            )
        );
    }

    public function wordOperatorUnindentedGroup(): void
    {
        if (
            $this->isAdmin
            or (
            $this->isActive
            and $this->hasLicense
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

    public function matchSubjectUntouched(): void
    {
        // phpcbf must leave a `match` subject alone for the same reason: it is
        // an operand, not a group, so its "  " indent survives the fixer.
        if (
            $this->isAdmin
            || match (
              $this->isActive && $this->hasLicense
            ) {
                true => $this->allowed,
                default => false,
            }
        ) {
            $this->grant();
        }
    }

    public function closureParameterListUntouched(): void
    {
        // phpcbf must leave a closure parameter list alone: its "  " indent is
        // out of scope, so it appears identically in the fixed output.
        if (
            $this->isAdmin
            || (function (
              bool $granted = self::ACTIVE && self::LICENSED
            ): bool {
                return $granted;
            })()
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

    public function heredocOperandUntouched(): void
    {
        // phpcbf must leave heredoc body lines alone: they are string content,
        // so reindenting one would rewrite the value rather than the layout.
        // The surrounding group is deliberately unindented, so the fixer does
        // run here — it must move the real conditions and nothing else.
        if (
            $this->isAdmin
            || (
            $this->body === <<<'SQL'
select 1
from t
SQL
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function multiLineStringOperandUntouched(): void
    {
        // Same, for a wrapped double-quoted string inside an unindented group.
        if (
            $this->isAdmin
            || (
            $this->body === "select 1
from t"
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function mixedArrowOperandUntouched(): void
    {
        // An arrow function *mixed with* real conditions, rather than filling
        // the group on its own: the group is a group (the `&&` before `fn` is
        // its own), so the fixer does run here — and it must still move only
        // the two real conditions. The body of the `fn` swallows everything
        // after `=>`, so its wrapped continuation is not one of them and
        // appears identically in the fixed output.
        if (
            $this->isAdmin
            || (
            $this->isActive
            && fn (): bool => $this->hasLicense
                    && $this->isTrial
            )
        ) {
            $this->grant();
        }
    }

    public function concatenatedGroupIndented(): void
    {
        // A grouping directly after `.` is still a grouping. No other sniff in
        // the ruleset flags its indentation, so if `.` is not read as a place
        // an operand may begin, this misindentation ships unreported.
        if (
            $this->prefix . (
            $this->isActive
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function assignedGroupIndented(): void
    {
        // Same, for a grouping directly after `=`.
        if (
            $this->granted = (
            $this->isActive
            && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }
}
