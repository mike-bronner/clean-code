<?php

/**
 * The continuation invariant, in both directions.
 *
 * `elseif`, `else`, `catch` and `finally` continue a construct whose opener
 * sits at the same level. That is two separate claims, and the sniff spells
 * them out in two separate places:
 *
 * - they are in NESTING_TOKENS, so a control structure written inside one of
 *   those branches is counted at the branch's depth (the first four methods);
 * - they are *not* in register(), so a chain whose leading `if`/`try` is itself
 *   over the limit is reported once, at that opener, and not once per branch
 *   (the last four).
 *
 * The second half is the one nothing else pins. Every other fixture puts its
 * continuations at a compliant depth, where the omission from register() cannot
 * show: adding T_ELSEIF, T_ELSE, T_CATCH or T_FINALLY to register() leaves the
 * rest of the suite green and turns the counts here into 3 and 4.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\MethodNestingLevel;

class MethodNestingLevelContinuations
{
    /**
     * The `elseif` continues the chain at level 1, its `foreach` is level 2, and
     * the `while` inside is level 3 and reported. Dropping T_ELSEIF from
     * NESTING_TOKENS measures the `while` at 2 and reports nothing.
     */
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

    /**
     * The same shape inside a plain `else`. Dropping T_ELSE loses this one.
     */
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

    /**
     * The same shape inside a `finally`. Dropping T_FINALLY loses this one.
     */
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

    /**
     * A `do … while` nested in an `if` is level 2 and its `foreach` level 3.
     * Dropping T_DO from NESTING_TOKENS measures the `foreach` at 2.
     */
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

    /**
     * An over-limit `if` chain with both continuation spellings, each branch
     * holding plain statements only. One report, at the leading `if`. Registering
     * T_ELSEIF or T_ELSE gives three.
     */
    public function overLimitIfChainIsReportedOnce(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                if ($i > 0) {
                    $x = 1;
                } elseif ($i < 0) {
                    $x = 2;
                } else {
                    $x = 3;
                }
            }
        }
    }

    /**
     * The two-word spelling of the same chain: a bare `else` and a fresh `if`,
     * which is a continuation and not a nested `if`. One report, at the leading
     * `if`. Dropping the sniff's `else`-lookback gives three.
     */
    public function overLimitTwoWordElseIfChainIsReportedOnce(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                if ($i > 0) {
                    $x = 1;
                } else if ($i < 0) {
                    $x = 2;
                } else if ($i === 0) {
                    $x = 3;
                }
            }
        }
    }

    /**
     * The near miss the lookback must not swallow: an `if` written *inside* a
     * braced `else` block is a genuinely nested structure, not a continuation,
     * and it is reported at level 3. Widening the lookback from "the previous
     * token is `else`" to "the previous keyword is `else`" would lose this.
     */
    public function bracedIfInsideElseIsNotAContinuation(): void
    {
        foreach ($this->items as $i) {
            if ($this->a) {
                $x = 1;
            } else {
                if ($this->b) {
                    $x = 2;
                }
            }
        }
    }

    /**
     * An over-limit `try` with both of its continuations, each holding plain
     * statements. One report, at the `try`. Registering T_CATCH or T_FINALLY
     * gives three.
     */
    public function overLimitTryIsReportedOnce(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                try {
                    $i--;
                } catch (\Throwable $e) {
                    $i = 0;
                } finally {
                    $x = 1;
                }
            }
        }
    }
}
