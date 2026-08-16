<?php

/**
 * The one shape phpmd reports twice and this sniff reports once.
 *
 * pdepend hands phpmd both a trait node and, separately, a node per method of
 * that trait. ShortVariable is TraitAware as well as MethodAware, and its
 * per-name deduplication is reset between nodes, so a short parameter of a
 * trait method is reported by both passes — phpmd 2.15 prints the same
 * violation on the same line twice. A class method with the same shape is
 * reported once, because the class pass looks at fields only.
 *
 * Reporting it once is not falling silent: the violation is reported, and the
 * duplicate carries no information the first line does not.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

trait Doubled
{
    private $tf = 1;

    public function traited($tp): int
    {
        return $tp + $this->tf;
    }
}
