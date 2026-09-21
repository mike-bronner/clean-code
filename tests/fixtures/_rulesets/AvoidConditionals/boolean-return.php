<?php

declare(strict_types=1);

/**
 * The auto-fixable slice of "Conditionals: Avoid Conditionals" — the
 * boolean-return if, carried by
 * SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn as
 * wired into the master CleanCode/ruleset.xml.
 *
 * Two shapes are fixable (the condition already evaluates to a boolean, so
 * collapsing it cannot change the return type) and one is not (a truthy
 * non-boolean condition, which `return $value;` would widen from bool to
 * string). Keeping the unfixable shape here is the point: it proves the
 * "warned only, not auto-fixed" half of the standard rather than assuming it.
 */

final class Gate
{
    // Fixable: early-return form, comparison condition.
    public function isLarge(int $amount): bool
    {
        if ($amount > 100) {
            return true;
        }

        return false;
    }

    // Fixable: if/else form, negated by the fixer because the if returns false.
    public function isSmall(int $amount): bool
    {
        if ($amount > 100) {
            return false;
        } else {
            return true;
        }
    }

    // Not fixable: $name is a string, not a boolean, so there is no safe
    // mechanical rewrite. Warned only.
    public function hasName(string $name): bool
    {
        if ($name) {
            return true;
        }

        return false;
    }
}
