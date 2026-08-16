<?php

/**
 * Over-nested code for CleanCode.Metrics.MethodNestingLevel.
 *
 * Two things are pinned here that a "flags something" fixture would not pin.
 * Each excess control structure is reported at *its own* line, so the methods
 * below carry more than one violation where more than one structure is over the
 * limit; and every token in register() is the deepest, self-reported construct
 * somewhere in this file, so deleting any one of them reddens the suite rather
 * than being carried by a different token's report.
 *
 * process() is passing.php's boundaryAtTwoLevels() with one loop added — the
 * boundary pair, the same method restructured from exactly 2 levels to exactly 3.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\MethodNestingLevel;

class MethodNestingLevelFailing
{
    /**
     * The boundary case: passing.php's boundaryAtTwoLevels() restructured to 3.
     */
    public function process(): void
    {
        foreach ($this->items as $item) {
            if ($item->isReady()) {
                while ($item->hasNext()) {
                    $item->run();
                }
            }
        }
    }

    public function threeLevels(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                while ($item > 0) {
                    $item--;
                }
            }
        }
    }

    /**
     * Two structures over the limit, so two reports — the `while` at 3 and the
     * `foreach` at 4. A sniff reporting once per method would give one.
     */
    public function fourLevels(): void
    {
        if ($this->flag) {
            for ($i = 0; $i < 10; $i++) {
                while ($i > 0) {
                    foreach ($this->items as $item) {
                        $i--;
                    }
                }
            }
        }
    }

    /**
     * The closure is the third level and its body's `if` the fourth, so both are
     * reported. passing.php's closureBodyIsLevelTwo() is the compliant twin.
     */
    public function nestedClosureBody(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $fn = function () use ($item): void {
                    if ($item) {
                        echo $item;
                    }
                };
            }
        }
    }

    /**
     * The `switch` is at level 2 and passes; the `if` inside its `case` is at 3
     * and is reported. The `case` label itself adds nothing, so a single report.
     */
    public function switchInsideLoop(): void
    {
        for ($i = 0; $i < 10; $i++) {
            switch ($this->value) {
                case 1:
                    if ($this->flag) {
                        $i = 0;
                    }
                    break;
            }
        }
    }

    /**
     * The `try` is level 3 and reported at the `try`; the `if` inside the
     * `catch` is level 4. The `catch` itself is never reported — it continues
     * the `try` — which continuations.php pins in its own right.
     */
    public function tryInsideNestedLoops(): void
    {
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->items as $item) {
                try {
                    $item->run();
                } catch (\Throwable $e) {
                    if ($this->flag) {
                        echo 'x';
                    }
                }
            }
        }
    }

    public function matchInsideNestedLoops(): void
    {
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->items as $item) {
                $result = match ($item) {
                    1 => 'one',
                    default => 'other',
                };
            }
        }
    }

    /**
     * `for` as the deepest construct, its body plain statements only. Nothing
     * inside it can carry the report, so deleting T_FOR from register() drops
     * this one.
     */
    public function forIsReportedAtItsOwnLine(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                for ($j = 0; $j < 10; $j++) {
                    $j += $i;
                }
            }
        }
    }

    /**
     * `switch` as the deepest construct — a bare `case`/`break` body, so only
     * the `switch` itself can be reported. Deleting T_SWITCH drops this one.
     */
    public function switchIsReportedAtItsOwnLine(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                switch ($i) {
                    case 1:
                        break;
                }
            }
        }
    }

    /**
     * `do … while` as the deepest construct, reported at the `do` and not at the
     * trailing `while`. Deleting T_DO drops this one.
     */
    public function doIsReportedAtItsOwnLine(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                do {
                    $i--;
                } while ($i > 0);
            }
        }
    }

    /**
     * `while` as the deepest construct, so its report belongs to T_WHILE rather
     * than to anything nested inside it.
     */
    public function whileIsReportedAtItsOwnLine(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                while ($i > 0) {
                    $i--;
                }
            }
        }
    }

    /**
     * `try` as the deepest construct, with plain statements in both blocks.
     */
    public function tryIsReportedAtItsOwnLine(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                try {
                    $i--;
                } catch (\Throwable $e) {
                    $i = 0;
                }
            }
        }
    }

    /**
     * `try` in its other role — a level for what its *own* block holds, rather
     * than a construct being reported. The `foreach` is level 3. Every other
     * `try` in the suite is either the reported construct itself or carries its
     * nesting in a `catch`/`finally` branch, so dropping T_TRY from
     * NESTING_TOKENS is visible only here.
     */
    public function nestingInsideTryBlock(): void
    {
        if ($this->a) {
            try {
                foreach ($this->items as $i) {
                    $i--;
                }
            } catch (\Throwable $e) {
                $x = 1;
            }
        }
    }

    /**
     * `match` in the same second role. A match arm holds an expression, so the
     * only control structure that can nest directly inside one is another
     * `match`: the third here is level 3 and reported, the outer two are 1 and 2
     * and are not. Dropping T_MATCH from NESTING_TOKENS measures all three at 1.
     */
    public function nestedMatchArmsAreLevels(): void
    {
        $result = match ($this->value) {
            default => match ($this->other) {
                default => match ($this->third) {
                    default => 0,
                },
            },
        };
    }
}
