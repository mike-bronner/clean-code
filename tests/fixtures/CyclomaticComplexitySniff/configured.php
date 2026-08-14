<?php

declare(strict_types=1);

/**
 * The `reportLevel` property, exercised at a value no default would reach.
 * modest() measures 5 and quiet() measures 4, so a report level of 5 reports
 * exactly one of them and a report level of 6 reports neither — the same
 * at-or-above comparison the default makes, proved at a number this file can
 * state exactly.
 */

class Configured
{
    // 5 — 1 plus two ifs and two &&.
    public function modest(bool $a, bool $b, int $n): int
    {
        if ($a && $b) {
            return 1;
        }

        if ($n > 0 && $a) {
            return 2;
        }

        return 0;
    }

    // 4 — one below modest(), so a level of 5 has to leave it alone.
    public function quiet(bool $a, bool $b, int $n): int
    {
        if ($a && $b) {
            return 1;
        }

        if ($n > 0) {
            return 2;
        }

        return 0;
    }
}
