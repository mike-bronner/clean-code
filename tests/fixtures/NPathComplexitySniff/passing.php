<?php

declare(strict_types=1);

namespace App\Fixtures;

// Nothing here reaches the default minimum of 200, so this file must produce
// zero violations.
//
// atOneBelowTheMinimum() sits one under it — the near miss the threshold has to
// stay silent on — and uncountedConstructs() is stuffed with the constructs
// PDepend deliberately does not score, so a sniff that counted any of them
// would push it over on its own.
//
// The small callables after them are small on purpose. Their job is not the
// threshold but the measurement: each pins one counting rule the two above
// never reach, so the exact-count assertion in
// tests/Standards/NPathComplexityTest.php moves if that rule breaks. Every
// number in their docblocks came from a live PHPMD 2.15.0 run over this file.

/**
 * 199: the `if` scores 0 for its condition, plus its body, plus 1 because the
 * chain has no `else`. The body is a sequence, so it multiplies: 2 for the
 * inner `if`, 9 for the nine-label switch, 11 for the eleven-label switch, or
 * 198 — and 198 + 1 is 199.
 *
 * NPath multiplies where cyclomatic complexity adds, which is the whole point
 * of the metric: this is 199 paths through 24 decision points.
 */
function atOneBelowTheMinimum(int $a): void
{
    if ($a > 0) {
        if ($a > 1) {
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
            case 11: echo 11; break;
        }
    }
}

/**
 * 2, all of it from the one `if` guarding the `goto`. Everything else here is a
 * construct PDepend does not score: `match` and its arms, `??`, `??=`, `?->`,
 * `!`, `goto` and its label, and a first-class callable. Counting any one of
 * them would move this off 2.
 *
 * The boolean operators in the string, the comment, and the heredoc are there
 * so a sniff that scanned text rather than tokens would score them — none of
 * the three is a boolean operator token, so all three must be worth nothing.
 */
function uncountedConstructs(int $a, ?object $b): mixed
{
    $c = $a ?? 1;
    $c ??= 2;
    $d = $b?->value;
    $e = !$a;
    $f = 'a && b || c xor d';
    $g = strlen(...);
    $h = <<<'TEXT'
        && || xor
        TEXT;
    // && || xor
    $i = match ($a) {
        1 => 'one',
        2 => 'two',
        default => 'many',
    };

    if ($e) {
        goto done;
    }

    done:

    return [$c, $d, $f, $g, $h, $i];
}

/**
 * The two bodiless shapes, which a live PHPMD run treats differently:
 *
 * - abstractMethod() is measured and scores 1, the same as an empty concrete
 *   method.
 * - interfaceMethod() is not measured at all. PHPMD's method rules walk the
 *   methods of classes and traits, not of interfaces, so it is reported at no
 *   threshold — including a threshold of 1, where abstractMethod() is.
 *
 * Both belong in the compliant fixture either way: 1 cannot reach the default
 * minimum of 200.
 */
abstract class BodilessMethods
{
    abstract public function abstractMethod(int $a): void;
}

interface BodilessInterface
{
    public function interfaceMethod(int $a): void;
}

/**
 * 1. The body of an anonymous class is its own artifact, and a live PHPMD run
 * reports neither the anonymous class nor its methods. The inner method is
 * worth 4 on its own, so a sniff that folded it into the enclosing method would
 * score this 4, and one that reported it separately would raise a violation
 * PHPMD never raises.
 *
 * The `;` inside that body is the reason the statement-end scan has to jump
 * over braces rather than stop at the first semicolon it meets.
 */
function anonymousClassBody(int $a): object
{
    return new class {
        public function inner(int $b): void
        {
            if ($b > 0) {
                echo 1;
            }

            if ($b > 1) {
                echo 2;
            }
        }
    };
}

/**
 * 0. A `switch` scores its expression plus one range per label and nothing for
 * the construct itself, so a `switch` carrying no labels scores 0 — and 0
 * multiplied into the sequence zeroes the whole callable. PDepend does exactly
 * this, and a measurement of 0 can never reach a threshold of 1 or more, so the
 * callable stays silent rather than being reported as trivially complex.
 */
function labellessSwitch(int $a): void
{
    if ($a > 0) {
        echo 1;
    }

    switch ($a) {
    }
}

/**
 * 2. A closure and an arrow function are not their own artifacts: their
 * statements belong to the enclosing callable. Only the arrow function's
 * ternary scores here, so this is 2 rather than the 4 it would be if the
 * closure's own `if` were counted twice, or the 1 it would be if closure bodies
 * were skipped the way a nested named function's body is.
 */
function inlineClosures(int $a): callable
{
    $ternary = fn (int $b): int => $b > 0 ? 1 : 2;

    return $ternary;
}

/**
 * 2. The `while` belonging to a `do … while` is consumed with the loop, so it
 * is never counted a second time as a loop of its own. Counting it twice would
 * make this 4.
 */
function doWhileLoop(int $a): void
{
    do {
        echo 1;
    } while ($a > 0);
}

/**
 * 3. `try` sums its blocks and adds nothing for the construct: 1 for the try
 * range, 1 for the catch range, 1 for the finally range. A `finally` is a range
 * like any other here, unlike in cyclomatic complexity where it scores nothing.
 */
function tryBlocksSum(int $a): void
{
    try {
        echo 1;
    } catch (\RuntimeException $e) {
        echo 2;
    } finally {
        echo 3;
    }
}

/**
 * 3. An `if`/`elseif` chain nests rather than summing flat: the outer `if`
 * scores 0 for its condition, 1 for its body, and the `elseif` measured whole,
 * which is itself 0 + 1 + 1 because that chain ends without an `else`. The
 * outer `if` is not charged the trailing 1 as well, because to PDepend an
 * `elseif` counts as having an else.
 */
function elseIfChain(int $a): void
{
    if ($a > 0) {
        echo 1;
    } elseif ($a > 1) {
        echo 2;
    }
}

/**
 * 4. The alternative syntax measures exactly as the braced form does, so this
 * matches a braced `if`/`elseif`/`else` plus the `foreach` around it: the chain
 * is 0 + 1 + (0 + 1 + 1) = 3, and the `foreach` adds its own 1 to that body.
 */
function alternativeSyntax(array $items): void
{
    foreach ($items as $item):
        if ($item):
            echo 1;
        elseif ($item):
            echo 2;
        else:
            echo 3;
        endif;
    endforeach;
}

/**
 * 2. A named function declared inside another callable is its own PHPMD
 * artifact. Its body is skipped here — this scores 1 for the enclosing function
 * — while the nested function is measured separately and scores 2 of its own.
 */
function nestedNamedFunction(int $a): void
{
    function nestedInner(int $b): void
    {
        if ($b > 0) {
            echo 1;
        }
    }

    if ($a > 0) {
        echo 1;
    }
}
