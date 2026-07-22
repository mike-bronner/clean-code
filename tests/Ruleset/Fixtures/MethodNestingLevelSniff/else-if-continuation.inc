<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelElseIfContinuation
{
    // Two-word `else if` is a continuation of the same chain, exactly like the
    // one-word `elseif`. A 3-level chain is reported once, at the leading `if` —
    // the `else if` segments do not each emit their own duplicate error.
    public function twoWordElseIfChain(): void
    {
        foreach ($this->outer as $o) {
            foreach ($this->items as $i) {
                if ($this->a) {
                    $x = 1;
                } else if ($this->b) {
                    $x = 2;
                } else if ($this->c) {
                    $x = 3;
                }
            }
        }
    }

    // The `else if` branch still adds exactly one nesting level for statements
    // inside it — the branch's `if` owns the block scope, so a control structure
    // nested inside it is counted, and a violation there is reported once.
    public function nestingInsideTwoWordElseIfBranch(): void
    {
        if ($this->a) {
            $x = 1;
        } else if ($this->b) {
            foreach ($this->items as $i) {
                while ($i > 0) {
                    $i--;
                }
            }
        }
    }
}
