<?php

declare(strict_types=1);

namespace App\Fixtures;

// Every callable here reaches the default minimum of 200, so each is reported
// once, on its own `function` keyword. The numbers in the docblocks came from a
// live PHPMD 2.15.0 run over this file.
//
// Each one is built so that a specific counting rule is load-bearing for the
// total rather than incidental to it: drop the rule and the callable falls
// below 200 instead of merely measuring differently, so the violation
// disappears and the test fails. That is what stops these from being satisfied
// by any sniff that merely counts something.

/**
 * 256. Eight independent `if`s in sequence, each worth 2, multiplied rather
 * than added — 2^8. A sniff that added decision points the way cyclomatic
 * complexity does would score this 9 and report nothing.
 */
function multipliesSequentialBranches(int $a): void
{
    if ($a > 1) {
        echo 1;
    }

    if ($a > 2) {
        echo 2;
    }

    if ($a > 3) {
        echo 3;
    }

    if ($a > 4) {
        echo 4;
    }

    if ($a > 5) {
        echo 5;
    }

    if ($a > 6) {
        echo 6;
    }

    if ($a > 7) {
        echo 7;
    }

    if ($a > 8) {
        echo 8;
    }
}

/**
 * 200. Two ten-label switches, worth 10 each, and one `if` worth 2. Nothing is
 * added for either `switch` construct itself and nothing for the missing
 * `default`, so each switch is exactly its label count — 10 × 10 × 2.
 *
 * This is also the boundary case at the default threshold: 200 is reported
 * because PHPMD compares `npath >= minimum`. atOneBelowTheMinimum() in
 * passing.php is the same shape one lower.
 */
function switchLabelsMultiply(int $a): void
{
    if ($a > 0) {
        echo 1;
    }

    switch ($a) {
        case 1: echo 1; break;
        case 2: echo 2; break;
        case 3: echo 3; break;
        case 4: echo 4; break;
        case 5: echo 5; break;
        case 6: echo 6; break;
        case 7: echo 7; break;
        case 8: echo 8; break;
        case 9: echo 9; break;
        case 10: echo 10; break;
    }

    switch ($a) {
        case 1: echo 1; break;
        case 2: echo 2; break;
        case 3: echo 3; break;
        case 4: echo 4; break;
        case 5: echo 5; break;
        case 6: echo 6; break;
        case 7: echo 7; break;
        case 8: echo 8; break;
        case 9: echo 9; break;
        case 10: echo 10; break;
    }
}

/**
 * 204: a seventeen-label switch, a ternary worth 2, an `xor` condition worth 3,
 * and a `return` of a two-operator boolean chain worth 2 — 17 × 2 × 3 × 2.
 *
 * Two counting rules are load-bearing here, and cyclomatic complexity gets both
 * the other way round:
 *
 * - `xor` scores 1, so the `if` is worth 3 rather than 2. PDepend ignores `xor`
 *   for cyclomatic complexity but counts it for NPath, and
 *   ExcessiveClassComplexitySniff in this same standard excludes it for that
 *   reason. Score it 0 here and the total falls to 136, below the threshold.
 * - a `return` multiplies its whole expression's boolean complexity into the
 *   sequence, so `return $b && $c && $a > 0;` is worth 2 where the same
 *   expression assigned to a variable is worth 1. Score it 1 and the total
 *   falls to 102, below the threshold.
 */
function keywordXorAndReturnChain(int $a, bool $b, bool $c): bool
{
    switch ($a) {
        case 1: echo 1; break;
        case 2: echo 2; break;
        case 3: echo 3; break;
        case 4: echo 4; break;
        case 5: echo 5; break;
        case 6: echo 6; break;
        case 7: echo 7; break;
        case 8: echo 8; break;
        case 9: echo 9; break;
        case 10: echo 10; break;
        case 11: echo 11; break;
        case 12: echo 12; break;
        case 13: echo 13; break;
        case 14: echo 14; break;
        case 15: echo 15; break;
        case 16: echo 16; break;
        case 17: echo 17; break;
    }

    $d = $a > 0 ? 1 : 2;

    if ($b xor $c) {
        echo $d;
    }

    return $b && $c && $a > 0;
}

/**
 * 207: a twenty-three-label switch and a `foreach` worth 9 — 23 × 9.
 *
 * The `foreach` is where the rule bites. Its body is a sequence holding the
 * closure's two `if`s and the arrow function's ternary, each worth 2, so the
 * body is 2 × 2 × 2 = 8 and the loop is 0 + 8 + 1 = 9. A closure and an arrow
 * function are not their own artifacts to PDepend: their statements belong to
 * the callable around them.
 *
 * A sniff that treated either as its own artifact — the way a nested *named*
 * function genuinely is — would measure the loop body as 1, making the
 * `foreach` worth 2 and the total 46, far below the threshold.
 */
function closureBodiesBelongToTheEnclosingCallable(int $a, array $items): callable
{
    switch ($a) {
        case 1: echo 1; break;
        case 2: echo 2; break;
        case 3: echo 3; break;
        case 4: echo 4; break;
        case 5: echo 5; break;
        case 6: echo 6; break;
        case 7: echo 7; break;
        case 8: echo 8; break;
        case 9: echo 9; break;
        case 10: echo 10; break;
        case 11: echo 11; break;
        case 12: echo 12; break;
        case 13: echo 13; break;
        case 14: echo 14; break;
        case 15: echo 15; break;
        case 16: echo 16; break;
        case 17: echo 17; break;
        case 18: echo 18; break;
        case 19: echo 19; break;
        case 20: echo 20; break;
        case 21: echo 21; break;
        case 22: echo 22; break;
        case 23: echo 23; break;
    }

    foreach ($items as $item) {
        $closure = function (int $b) use ($item): void {
            if ($b > 0) {
                echo $item;
            }

            if ($b > 1) {
                echo 2;
            }
        };

        $arrow = fn (int $c): int => $c > 0 ? 1 : 2;

        $closure($a);
        $arrow($a);
    }

    return fn (): int => 1;
}

/**
 * Carries the `else if` case, and proves the sniff reports a method the same
 * way it reports a free function.
 */
class NestedDeclarations
{
    /**
     * 204: a seventeen-label switch, the `else if` chain worth 3, and the
     * `do … while` worth 4 — 17 × 3 × 4.
     *
     * `else if` written with a space is one construct to PDepend and scores
     * exactly as `elseif` does, so the chain is 0 for the condition, 1 for the
     * body, and 2 for the `else if` measured whole — which is itself 0 + 1 + 1.
     * A sniff that measured the `else` as an ordinary body and then counted the
     * `if` again as a statement in its own right would multiply the two instead
     * of adding them, and would not agree with PHPMD here.
     *
     * The `do … while` is 4: its body holds one `if` worth 2, its condition
     * carries one `&&` worth 1, and the loop adds 1.
     */
    public function elseIfWithSpace(int $a, array $items): void
    {
        switch ($a) {
            case 1: echo 1; break;
            case 2: echo 2; break;
            case 3: echo 3; break;
            case 4: echo 4; break;
            case 5: echo 5; break;
            case 6: echo 6; break;
            case 7: echo 7; break;
            case 8: echo 8; break;
            case 9: echo 9; break;
            case 10: echo 10; break;
            case 11: echo 11; break;
            case 12: echo 12; break;
            case 13: echo 13; break;
            case 14: echo 14; break;
            case 15: echo 15; break;
            case 16: echo 16; break;
            case 17: echo 17; break;
        }

        if ($a > 1) {
            echo 1;
        } else if ($a > 2) {
            echo 2;
        } else {
            echo 3;
        }

        do {
            if ($a > 3) {
                echo 4;
            }
        } while ($a > 0 && $a < 10);
    }
}
