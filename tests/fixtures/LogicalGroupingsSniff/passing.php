<?php

declare(strict_types=1);

namespace App\Fixtures;

class Passing
{
    private const ACTIVE = true;

    private const LICENSED = true;

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

    /**
     * Three levels, and three conditions in the innermost group: the second
     * and third conditions of a group are measured by a different branch than
     * the first, and the third level is the one that proves the expected
     * indent is derived from the immediate parent rather than a fixed depth.
     */
    public function threeLevelNestedGroups(): void
    {
        if (
            $this->isAdmin
            || (
                $this->isActive
                && (
                    $this->hasLicense
                    || (
                        $this->isTrial
                        && $this->withinTrialWindow
                        && $this->hasSeat
                    )
                )
            )
        ) {
            $this->grant();
        }
    }

    /**
     * The word operators are a separate token set in the vendored PHPCS
     * (T_LOGICAL_AND/T_LOGICAL_OR rather than T_BOOLEAN_AND/T_BOOLEAN_OR), and
     * composer.json accepts a range of PHPCS versions, so detection through
     * them is pinned rather than assumed.
     */
    public function wordOperatorGroup(): void
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

    public function doWhileGroup(): void
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

    public function matchSubjectIgnored(): void
    {
        // A `match` subject is an operand, not a condition grouping. Even when
        // it wraps across lines and carries a boolean, the subject is out of
        // scope — the "  " subject indent below would be flagged if the
        // parenthesis after `match` were read as a group.
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

    public function closureParameterListIgnored(): void
    {
        // A closure's parameter list is not a condition grouping either. Its
        // default value carries a boolean and it wraps across lines, so the
        // "  " parameter indent below would be flagged if it were read as one.
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

    public function heredocOperandIgnored(): void
    {
        // PHPCS splits a heredoc/nowdoc body into one token per physical line,
        // and each of those tokens begins its line. They are string content,
        // not conditions: the column-0 body lines below would each be flagged
        // as a misaligned condition, and reindenting one would rewrite the
        // value the fixture asserts on.
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

    public function multiLineStringOperandIgnored(): void
    {
        // Same split, same reasoning, for a double-quoted string that wraps:
        // the continuation line is string content, not a grouped condition.
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
}
