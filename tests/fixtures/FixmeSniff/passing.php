<?php

// The fixmes column in the old tracker was dropped before release.
# The prefixme placeholder was renamed years ago.

/* XXX belongs to CleanCode.Commenting.DebtMarkers, so this sniff stays quiet */
/*
 * TODO belongs to Generic.Commenting.Todo, likewise.
 */

/**
 * A subscription with no known defect.
 *
 * HACK is the fourth marker, and it is not this sniff's either.
 *
 * @param string $fixmeQueueName The queue this subscription was drawn from.
 */
class Passing
{
    // The affixment of a label is unrelated.
    public function total(string $fixmeQueueName): int
    {
        return strlen($fixmeQueueName);
    }
}
