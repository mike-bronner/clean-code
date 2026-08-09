<?php

declare(strict_types=1);

namespace App\Fixtures;

// Both classes reach the default maximum of 50.
//
// AtExactlyTheMaximum is the boundary: PHPMD reports a class whose weighted
// method count is at or above the maximum, not strictly above it, so 50 is a
// violation and 49 (tests/fixtures/ExcessiveClassComplexitySniff/passing.php)
// is not.

class AtExactlyTheMaximum
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
     * Pads the class to a weighted method count of exactly 50: 13 above plus 1 for this method and 36 boolean operators.
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
            && $flag && $flag && $flag && $flag && $flag
            && $flag;
    }
}

class WellAboveTheMaximum
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
     * Pads the class to a weighted method count of 63: 13 above plus 1 for this method and 49 boolean operators.
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
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag;
    }
}
