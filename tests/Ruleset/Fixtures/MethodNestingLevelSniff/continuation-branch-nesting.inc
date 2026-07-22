<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelContinuationBranchNesting
{
    // A control structure nested inside an `elseif` branch is counted at the
    // correct depth: the `elseif` continues the chain at level 1, its `foreach`
    // is level 2, and the inner `while` is level 3 and reported.
    public function nestingInsideElseif(): void
    {
        if ($this->a) {
            $x = 1;
        } elseif ($this->b) {
            foreach ($this->items as $i) {
                while ($i > 0) {
                    $i--;
                }
            }
        }
    }

    // Same shape inside a plain `else` branch: the `while` is level 3.
    public function nestingInsideElse(): void
    {
        if ($this->a) {
            $x = 1;
        } else {
            foreach ($this->items as $i) {
                while ($i > 0) {
                    $i--;
                }
            }
        }
    }

    // A control structure nested inside a `finally` block is counted: `finally`
    // continues the `try` at level 1, the `foreach` is level 2, the inner
    // `while` is level 3 and reported.
    public function nestingInsideFinally(): void
    {
        try {
            $x = 1;
        } finally {
            foreach ($this->items as $i) {
                while ($i > 0) {
                    $i--;
                }
            }
        }
    }

    // `do { } while ()` adds a nesting level like any other loop: nested inside
    // an `if`, the `do` is level 2 and the inner `foreach` is level 3, reported.
    public function nestingInsideDoWhile(): void
    {
        if ($this->a) {
            do {
                foreach ($this->items as $i) {
                    $i--;
                }
            } while ($this->a);
        }
    }
}
