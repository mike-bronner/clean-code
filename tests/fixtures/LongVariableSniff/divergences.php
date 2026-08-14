<?php

declare(strict_types=1);

/**
 * The two places this sniff deliberately does not match PHPMD 2.15.0. Unlike
 * the other fixtures here, this file is not a parity claim — it exists so both
 * divergences are pinned by a test and cannot drift unnoticed.
 *
 * Both are set out in docs/phpmd/naming-longvariable.md.
 */

/**
 * Divergence 1 — a trait's parameters and locals are reported once here, twice
 * by PHPMD.
 *
 * PHPMD's `apply()` returns early after the fields of a `class` node, but a
 * `trait` node falls through to the general branch and walks the whole trait,
 * parameters and bodies included. Every method is then walked again as a node
 * in its own right, so a trait's parameters and locals come back twice. The
 * field is reported once either way. Emitting the same violation twice helps
 * nobody, so the duplicate is dropped rather than reproduced.
 */
trait ReplenishmentAuditing
{
    protected string $replenishmentWindowId = '';

    public function audit(int $scheduledOrderCounter): int
    {
        $warehouseLocationKeys = $scheduledOrderCounter;

        return $warehouseLocationKeys;
    }
}

/**
 * Divergence 2 — a name that only ever appears interpolated into a
 * double-quoted string or a heredoc is reported by PHPMD and not by this sniff.
 *
 * PHPCS hands a sniff the whole string as a single token, so there is no
 * T_VARIABLE to find and no line to report. This is a limitation of working
 * from tokens rather than a choice. It is narrow in practice: a variable that
 * is assigned or declared anywhere in the same container is still reported at
 * that occurrence, and a variable that is *only* ever interpolated has no
 * declaration in the file at all.
 */
class ReplenishmentInterpolation
{
    public function render(): string
    {
        return "window {$inventoryAdjustmentId} closed by $shipmentManifestLines";
    }
}
