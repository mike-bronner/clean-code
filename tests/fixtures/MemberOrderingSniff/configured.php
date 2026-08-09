<?php

/**
 * The two properties a consuming ruleset can set, exercised together: a base
 * class this package has never heard of, and a relation class of the
 * consumer's own.
 */

declare(strict_types=1);

/**
 * Only the configured parent is a model, and only the configured return type
 * is a relation. Neither method reports under the shipped defaults.
 */
class ConfiguredModel extends Entity
{
    public function zulu(): Association
    {
        return $this->associate(Author::class);
    }

    public function alpha(): Association
    {
        return $this->associate(Editor::class);
    }
}

/**
 * The configured list replaces the shipped one rather than adding to it, so a
 * class extending one of the shipped parents is no longer a model here and its
 * reversed members go unreported. The parent is Pivot rather than Model
 * because the "*Model" suffix rule matches a parent named Model whatever the
 * list says, and would keep this class gated in.
 */
class ShippedParentModel extends Pivot
{
    public string $zulu = '';

    public string $alpha = '';
}
