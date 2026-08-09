<?php

declare(strict_types=1);

/**
 * Every declaration here measures at or above the default report level of 10.
 * The exact complexity of each, the exact line it is reported on, and the exact
 * number of reports are asserted in
 * tests/Standards/CyclomaticComplexityTest.php against a live PHPMD 2.15.0 run
 * over this same file.
 */

class Reported
{
    /**
     * 10 — exactly the default report level, which PHPMD reports because its
     * comparison is `$ccn < $threshold`, not `<=`. Deliberately spread over
     * many lines, with the declaration on a line of its own and the decision
     * points well below it, so the assertion on the reported line means
     * something.
     */
    public function atExactlyTheReportLevel(int $n): int
    {
        if ($n === 1) {
            return 1;
        }

        if ($n === 2) {
            return 2;
        }

        if ($n === 3) {
            return 3;
        }

        if ($n === 4) {
            return 4;
        }

        if ($n === 5) {
            return 5;
        }

        if ($n === 6) {
            return 6;
        }

        if ($n === 7) {
            return 7;
        }

        if ($n === 8) {
            return 8;
        }

        if ($n === 9) {
            return 9;
        }

        return 0;
    }

    /**
     * 12 — one if plus ten `&&`. Each operator scores on its own, so a chained
     * expression is worth one per operator rather than one per expression.
     * booleanChainRemoved() in passing.php is the paired control: the same
     * single if with the chain taken out measures 2.
     */
    public function booleanOperatorChain(bool $a, bool $b, bool $c): bool
    {
        if ($a && $b && $c && $a && $b && $c && $a && $b && $c && $a && $b) {
            return true;
        }

        return false;
    }

    /**
     * 11 — four in the method itself, seven more in the closure it returns.
     * PDepend walks a method's whole subtree, so the closure's decision points
     * are scored against the method and the closure is never reported under a
     * name of its own. Split them apart and the method measures 4, which the
     * default report level passes over in silence — which is what makes this
     * fixture discriminating rather than merely large.
     */
    public function mergesItsClosure(array $rows): callable
    {
        if ($rows === []) {
            $rows = [1];
        }

        if (count($rows) > 10) {
            $rows = [];
        }

        if ($rows === [1]) {
            $rows = [1, 2];
        }

        return static function (int $n, bool $a, bool $b) use ($rows): int {
            if ($n === 1 && $a) {
                return 1;
            }

            if ($n === 2 && $b) {
                return count($rows);
            }

            if ($n === 3 && $a) {
                return 3;
            }

            if ($n === 4) {
                return 4;
            }

            return 0;
        };
    }

    /**
     * 19 — genuinely nested rather than a flat run of ifs, three levels deep
     * in two of its arms: a switch holding four cases, an `if` holding an
     * `if` inside a `for` inside the first arm, a `foreach` holding a `while`
     * holding a ternary and an `if` in the second, a `catch` around the third,
     * and a `do … while` holding an `elseif` chain in the fourth.
     */
    public function deeplyNested(int $mode, array $rows): int
    {
        $total = 0;

        switch ($mode) {
            case 1:
                for ($i = 0; $i < 10; $i++) {
                    if ($i % 2 === 0 && $i > 2) {
                        if ($i > 6) {
                            $total += $i;
                        }
                    }
                }

                break;
            case 2:
                foreach ($rows as $row) {
                    while ($row > 0) {
                        $total += $row > 5 ? 2 : 1;

                        if ($row === 3) {
                            $total++;
                        }

                        $row--;
                    }
                }

                break;
            case 3:
                try {
                    $total = (int) array_sum($rows);
                } catch (Throwable $e) {
                    $total = $total > 0 || $rows === [] ? 0 : -1;
                }

                break;
            case 4:
                do {
                    if ($total > 5) {
                        $total--;
                    } elseif ($total < 0) {
                        $total++;
                    }
                } while ($total !== 0);

                break;
        }

        return $total;
    }
}

/**
 * 10 — a standalone function, reported in its own right by PHPMD's
 * FunctionAware half. Built from a mix of constructs rather than a run of ifs,
 * so it also exercises the counted list at the report level.
 */
function heavyStandalone(array $rows, bool $flag): int
{
    $total = 0;

    foreach ($rows as $row) {
        if ($row > 10 && $flag) {
            $total += $row;
        } elseif ($row < 0 || $flag) {
            $total -= $row;
        }
    }

    while ($total > 100) {
        $total = $total > 200 ? 0 : $total - 1;
    }

    if ($total === 0 && $flag) {
        $total = 1;
    }

    return $total;
}
