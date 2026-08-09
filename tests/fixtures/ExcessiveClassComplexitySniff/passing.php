<?php

declare(strict_types=1);

namespace App\Fixtures;

// Nothing here reaches the default maximum of 50, so this file must produce
// zero violations.
//
// AtOneBelowTheMaximum sits one under it — the near miss the threshold has to
// stay silent on — and UncountedConstructs is stuffed with the constructs
// PDepend deliberately does not score, so a sniff that counted any of them
// would push it over on its own.

class AtOneBelowTheMaximum
{
    /**
     * 13: 1 for the method, plus 12 decision points — if, &&, foreach, the
     * ternary, elseif, ||, for, while, the `while` of the do-while, two cases,
     * and the catch. The `else`, the `default`, and the `finally` add nothing,
     * matching PDepend.
     */
    public function varied(int $a, int $b, array $items): int
    {
        if ($a && $b) {
            foreach ($items as $item) {
                $a += $item ? 1 : 0;
            }
        } elseif ($a || $b) {
            for ($i = 0; $i < $b; $i++) {
                $a--;
            }
        } else {
            while ($a > 0) {
                $a--;
            }
        }

        do {
            $b--;
        } while ($b > 0);

        try {
            switch ($a) {
                case 1:
                    break;
                case 2:
                    break;
                default:
                    break;
            }
        } catch (\Throwable) {
            $a = 0;
        } finally {
            $b = 0;
        }

        return $a + $b;
    }

    /**
     * Pads the class to a weighted method count of exactly 49: 13 above plus 1 for this method and 35 boolean operators.
     */
    public function padding(bool $flag): bool
    {
        return $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag;
    }
}

class UncountedConstructs
{
    /**
     * Every construct below is one PDepend does not count. There are far more
     * than 50 of them, so this method is worth 1 and this class is worth 4 —
     * the other 3 come from elseOnly() below.
     */
    public function uncounted(?int $a, ?object $o): int
    {
        $b = $a ?? 1;
        $b ??= 2;
        $c = $a ?? $b ?? 1 ?? 2 ?? 3 ?? 4 ?? 5 ?? 6 ?? 7 ?? 8 ?? 9 ?? 10;
        $d = $a ?? $b ?? 1 ?? 2 ?? 3 ?? 4 ?? 5 ?? 6 ?? 7 ?? 8 ?? 9 ?? 10;
        $e = $a xor $b;
        $e = ($a xor $b) xor true;
        $o?->one?->two?->three?->four?->five;

        $f = match ($b) {
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            11 => 11,
            12 => 12,
            13 => 13,
            14 => 14,
            15 => 15,
            16 => 16,
            17 => 17,
            18 => 18,
            19 => 19,
            20 => 20,
            default => 0,
        };

        switch ($f) {
            default:
                $f = 0;
                break;
        }

        try {
            $f++;
        } finally {
            $f--;
        }

        goto done;

        done:

        return $b + $c + $d + (int) $e + $f;
    }

    /**
     * The `else` half of a chain, over and over. Only the two `if`s score, so
     * this method is worth 3.
     */
    public function elseOnly(int $a): int
    {
        if ($a > 0) {
            $a++;
        } else {
            $a--;
        }

        if ($a > 0) {
            $a++;
        } else {
            $a--;
        }

        return $a;
    }
}

abstract class AbstractMethods
{
    /**
     * PDepend scores a method with no body 1, the same as a concrete one with
     * an empty body, so this class is worth 4 in both tools.
     */
    abstract public function first(): void;

    abstract public function second(): void;

    abstract public function third(): void;

    public function fourth(): void
    {
    }
}
