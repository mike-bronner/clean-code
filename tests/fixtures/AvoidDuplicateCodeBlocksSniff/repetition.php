<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * A column of same-shaped statements is one run of similar lines, not two
 * blocks. The sniff only reports a repeat once it sits a whole window clear of
 * the block it repeats, so a run has to be at least twice the window long
 * before any of it is a copy of any other part of it.
 *
 * seedCounters() holds nine such lines and is silent. seedLabels() holds twelve,
 * of a different shape — every line there appends a string, not an integer —
 * and both the first five and the five standing clear of them are reported.
 *
 * Twelve rather than ten, so the report's extent pins the second half of the
 * same rule: a matched block grows only as far as it can without reaching back
 * into the block it repeats. Lines 47-51 repeat lines 42-46, and one more line
 * of growth would put the original at 42-47 and overlap the copy. Letting it
 * grow freely runs the report on to line 53 instead of stopping at 51.
 *
 * Dropping the overlap guard reports seedCounters() too.
 */
class Repetition
{
    private function seedCounters(): void
    {
        $first[] = 1;
        $second[] = 1;
        $third[] = 1;
        $fourth[] = 1;
        $fifth[] = 1;
        $sixth[] = 1;
        $seventh[] = 1;
        $eighth[] = 1;
        $ninth[] = 1;
    }

    private function seedLabels(): void
    {
        $alpha[] = 'label';
        $bravo[] = 'label';
        $charlie[] = 'label';
        $delta[] = 'label';
        $echoed[] = 'label';
        $foxtrot[] = 'label';
        $golf[] = 'label';
        $hotel[] = 'label';
        $india[] = 'label';
        $juliett[] = 'label';
        $kilo[] = 'label';
        $lima[] = 'label';
    }
}
