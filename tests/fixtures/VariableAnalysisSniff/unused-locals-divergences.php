<?php

declare(strict_types=1);

namespace App;

/**
 * Every case where the configured sniff and PHPMD's UnusedLocalVariable rule
 * do not behave identically, plus the shape both tools miss. Each was checked
 * by running both tools over this file: PHPMD 2.15.0 with only
 * UnusedLocalVariable enabled, and phpcs with the master CleanCode/ruleset.xml.
 *
 * The two tools agree on *which names* are unused. They part company on how
 * many times to say so, because PHPMD reports a name once — at its first
 * assignment — while the sniff reports every assignment to it.
 *
 * This file is not compliant code. It is the record of the gap, kept as a
 * fixture so the gap is pinned by a test rather than only described in prose.
 */
class UnusedLocalsDivergences
{
    /**
     * Both tools call $total unused. PHPMD reports line 31 alone; the sniff
     * reports 31 and 32, one per assignment.
     *
     * The sniff is more useful here: each assignment is its own dead store,
     * and a reader fixing only the first would still be left with the second.
     */
    public function reassignedNeverRead(): string
    {
        $total = 1;
        $total = 2;

        return 'result';
    }

    /**
     * Neither tool flags this line — a shared blind spot, not a divergence
     * between them. $counter is read on line 49, but only to compute its own
     * next value, and nothing ever reads the result. Both tools count that
     * read as a use and stay silent.
     *
     * Detecting it needs dead-store analysis, which is beyond both. This shape
     * stays code review.
     */
    public function selfReferentialDeadStore(): string
    {
        $counter = 1;
        $counter = $counter + 1;

        return 'result';
    }
}
