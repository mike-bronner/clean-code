<?php

declare(strict_types=1);

/**
 * The de-duplication/member-access ordering quirk, reproduced from PHPMD rather
 * than corrected.
 *
 * PHPMD marks a name as seen *before* it decides whether to exempt the
 * occurrence — `checkNodeImage()` calls `addProcessed()` and only then
 * `checkMaximumLength()`, which is what returns early for a member access. So
 * whether an over-long name is reported at all depends on what its *first*
 * occurrence in the container happens to be.
 *
 * The two methods below are the same name-length case either way round, and
 * PHPMD 2.15.0 reports the second one only.
 */
class ReplenishmentOrdering
{
    /**
     * The first occurrence is the object of a member access, so the name is
     * marked as seen and then exempted. The assignment and the read that follow
     * are never reached, and the whole method stays silent.
     */
    public function memberAccessFirst(): void
    {
        $replenishmentWindowId->refresh();

        $replenishmentWindowId = 1;

        echo $replenishmentWindowId;
    }

    /**
     * The same name, with the assignment first. That occurrence is not a member
     * access, so it is measured and reported; the member access that follows is
     * already de-duplicated away.
     */
    public function memberAccessLater(): void
    {
        $scheduledOrderCounter = 1;

        $scheduledOrderCounter->refresh();
    }
}
