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
 * of a different shape — integer and string literals are different token types
 * — and both the first five and the five standing clear of them are reported.
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
        $this->first = 1;
        $this->second = 2;
        $this->third = 3;
        $this->fourth = 4;
        $this->fifth = 5;
        $this->sixth = 6;
        $this->seventh = 7;
        $this->eighth = 8;
        $this->ninth = 9;
    }

    private function seedLabels(): void
    {
        $this->alpha = 'a';
        $this->bravo = 'b';
        $this->charlie = 'c';
        $this->delta = 'd';
        $this->echoed = 'e';
        $this->foxtrot = 'f';
        $this->golf = 'g';
        $this->hotel = 'h';
        $this->india = 'i';
        $this->juliett = 'j';
        $this->kilo = 'k';
        $this->lima = 'l';
    }
}
