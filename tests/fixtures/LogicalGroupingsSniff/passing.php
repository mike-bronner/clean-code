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

    public function mixedArrowOperandIgnored(): void
    {
        // An arrow function mixed with real conditions: the group qualifies as
        // a group here (the `&&` before `fn` belongs to it), so unlike
        // arrowOperandIgnored above, this group *is* measured. Its body still
        // is not: everything after `=>` is one operand, so the deliberately
        // odd continuation indent below must not be flagged as a misaligned
        // condition of the enclosing group.
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

    public function concatenatedGroupIgnored(): void
    {
        // A grouping after `.` is a grouping, so the compliant form has to be
        // left alone as much as the misindented one has to be flagged.
        if (
            $this->prefix . (
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function assignedGroupIgnored(): void
    {
        // Same, for a grouping after `=`.
        if (
            $this->granted = (
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }

    public function arrayValueOperandIgnored(): void
    {
        // The compliant form of the same four boundaries: a parenthesized
        // boolean that belongs to an array, a subscript, or a closure body
        // rather than to the condition around it.
        //
        // The continuation line inside each region is deliberately laid out
        // flat against its opener rather than one level deeper. That layout is
        // what makes these four discriminate: it is exactly what this sniff
        // reports when it reads a parenthesis as a condition grouping, so a
        // walk that stepped into the region would flag every one of them. The
        // standard has nothing to say about how an array value wraps, so the
        // silence has to come from the boundary, not from the indentation
        // happening to match what the sniff expects.
        if (
            $this->isAdmin
            || (
                $this->isActive
                && $this->options === [
                    'flag' => ($this->hasLicense
                    && $this->isTrial),
                ]
            )
        ) {
            $this->grant();
        }
    }

    public function arrayElementOperandIgnored(): void
    {
        // Same, for an array element reached through `,`.
        if (
            $this->isAdmin
            || (
                $this->isActive
                && $this->options === [$this->flag, ($this->hasLicense
                && $this->isTrial)]
            )
        ) {
            $this->grant();
        }
    }

    public function arrayOffsetOperandIgnored(): void
    {
        // Same, for a subscript expression.
        if (
            $this->isAdmin
            || (
                $this->isActive
                && $this->map[($this->hasLicense
                && $this->isTrial)]
            )
        ) {
            $this->grant();
        }
    }

    public function closureBodyOperandIgnored(): void
    {
        // Same, for an assignment inside a closure body.
        if (
            $this->isAdmin
            || (
                $this->isActive
                && (function (): bool {
                    $granted = ($this->hasLicense
                    && $this->isTrial);

                    return $granted;
                })()
            )
        ) {
            $this->grant();
        }
    }

    public function commentAfterGroupOpenerIgnored(): void
    {
        // Only a comment shares the group's opening line. The group's first
        // condition is the one below it, correctly indented, so nothing is
        // glued to the parenthesis — a sniff that measured the token directly
        // after `(` rather than the first condition would report this.
        //
        // It does not discriminate how the line cursor is seeded. Comments are
        // skipped before the cursor is read at all, so this case stays silent
        // whichever line the cursor starts on. What pins the seed is
        // `gluedFirstConditionOnOpenerLine` in failing.php.
        if (
            $this->isAdmin
            || ( // the licence pair
                $this->isActive
                && $this->hasLicense
            )
        ) {
            $this->grant();
        }
    }
}
