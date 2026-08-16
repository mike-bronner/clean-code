<?php

declare(strict_types=1);

/**
 * The one shape where this sniff and PHPMD disagree, kept out of passing.php
 * and failing.php so neither of those files claims parity it does not have.
 *
 * PDepend does not surface the methods of an anonymous class to a MethodAware
 * rule, so PHPMD 2.15.0 reports nothing here however complex they are — a live
 * run at `reportLevel` 1 lists only makes(), at 1. This sniff registers on
 * T_FUNCTION and so reports the anonymous class's method too. That is a gap in
 * PHPMD rather than a decision: the method really does hold eleven paths, and
 * reproducing the omission would mean writing code to suppress a true defect.
 * The extra report keeps this sniff a superset of PHPMD, never looser than it.
 */

class HoldsAnonymousClass
{
    // 1 — the anonymous class's body belongs to the anonymous class, so none
    // of heavy()'s decision points reach this method.
    public function makes(): object
    {
        return new class {
            // 11 — reported here, not by PHPMD.
            public function heavy(int $n): int
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

                if ($n === 10) {
                    return 10;
                }

                return 0;
            }
        };
    }
}
