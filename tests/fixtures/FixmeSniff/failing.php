<?php

// FIXME: the discount is applied twice for annual plans.
# FIXME the currency is hard-coded

/* FIXME - this reads the config on every call */
/*
 * FIXME
 */

/**
 * A subscription with a known defect.
 *
 * FIXME
 * FIXME: proration ignores mid-cycle upgrades.
 */
class Failing
{
    public function total(): int
    {
        return 1;
    }
}
