<?php

/**
 * Compliant code for CleanCode.Metrics.MethodNestingLevel, plus the near-miss
 * shapes the sniff has to stay silent on.
 *
 * Every method here sits at 2 levels or fewer, and most of them are one
 * counting rule away from 3 — so a rule that started counting something it
 * should not (a `case` label, an `else` continuation, the trailing `while` of a
 * `do … while`) reddens this file rather than passing it. boundaryAtTwoLevels()
 * is the same method failing.php restructures to exactly 3.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\MethodNestingLevel;

class MethodNestingLevelPassing
{
    public function straightLine(): void
    {
        $a = 1;
        $b = 2;
    }

    public function oneLevel(): void
    {
        if ($this->flag) {
            $a = 1;
        }
    }

    public function twoLevels(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $a = $item;
            }
        }
    }

    /**
     * The boundary case: exactly 2 levels, the last shape that passes.
     * failing.php's process() is this method with one loop added.
     */
    public function boundaryAtTwoLevels(): void
    {
        foreach ($this->items as $item) {
            if ($item->isReady()) {
                $item->run();
            }
        }
    }

    /**
     * `elseif` and `else` continue the chain at the leading `if`'s level rather
     * than nesting beneath it, so the whole chain is level 1. Counting either as
     * its own level makes the assignments level 2 and the chain reportable the
     * moment it is nested once.
     */
    public function ifElseChainStaysLevelOne(): void
    {
        if ($this->flag) {
            $a = 1;
        } elseif ($this->other) {
            $a = 2;
        } else {
            $a = 3;
        }
    }

    /**
     * Two-word `else if` — a bare `else` followed by a fresh `if` — is the same
     * chain, and measures the same as the one-word spelling above.
     */
    public function twoWordElseIfChainStaysLevelOne(): void
    {
        if ($this->flag) {
            $a = 1;
        } else if ($this->other) {
            $a = 2;
        } else if ($this->third) {
            $a = 3;
        }
    }

    /**
     * `case` and `default` belong to the enclosing `switch` and add nothing, so
     * the `if` inside the case is level 2. Counting the label makes it 3.
     */
    public function switchCaseCountsAsOneLevel(): void
    {
        switch ($this->value) {
            case 1:
                if ($this->flag) {
                    $a = 1;
                }
                break;
            default:
                $a = 0;
        }
    }

    /**
     * `catch` and `finally` continue the `try` at its level, so the `if` inside
     * the catch is level 2 rather than 3.
     */
    public function tryCatchFinallyAtLevelOne(): void
    {
        try {
            $a = 1;
        } catch (\Throwable $e) {
            if ($this->flag) {
                $a = 2;
            }
        } finally {
            $a = 3;
        }
    }

    /**
     * A `do … while` is one level, not two: the trailing `while` is part of the
     * same loop and PHPCS gives it no scope of its own. Counting it makes the
     * assignment level 3.
     */
    public function doWhileIsOneLevel(): void
    {
        do {
            if ($this->flag) {
                $a = 1;
            }
        } while ($this->flag);
    }

    /**
     * A closure is an anonymous function and counts as a level, so its body's
     * `if` is level 2 — the compliant twin of failing.php's nestedClosureBody().
     */
    public function closureBodyIsLevelTwo(): void
    {
        $fn = function (): void {
            if ($this->flag) {
                $a = 1;
            }
        };
    }

    /**
     * The arrow-function twin of the closure above, and the reason it is here:
     * an arrow function counts as a level for what its body holds exactly as a
     * closure does, so the `match` in this body is level 2 and stays silent.
     * arrow-functions.php carries the same shape one level deeper, where it is
     * reported.
     */
    public function arrowFunctionBodyIsLevelTwo(): void
    {
        $fn = fn () => match ($this->value) {
            1 => 'one',
            default => 'other',
        };
    }

    public function matchAtLevelOne(): void
    {
        $result = match ($this->value) {
            1 => 'one',
            default => 'other',
        };
    }
}

// Top-level script code is out of scope: the standard governs method bodies.
// This nesting would be level 3 inside a method, so a sniff that stopped
// requiring an enclosing function would report it here.
if (isset($GLOBALS['x'])) {
    foreach ($GLOBALS['items'] as $item) {
        while ($item > 0) {
            $item--;
        }
    }
}
