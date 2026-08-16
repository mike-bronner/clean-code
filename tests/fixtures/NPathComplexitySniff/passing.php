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

/**
 * 2. A `for` scores `1 + B(cond) + N(body)`, and only its *middle* clause is the
 * condition: PDepend's visitForStatement sums the children that are expressions,
 * and the init and update clauses are ASTForInit and ASTForUpdate nodes instead.
 * Both clauses here carry a boolean operator and neither is counted, so this
 * measures the same 2 it would with no operator at all. Counting them would make
 * it 4.
 */
function forIgnoresInitAndUpdateClauses(int $a, bool $b, bool $c): void
{
    for ($i = ($b && $c); $i < 10; $i++, $a = ($b || $c)) {
        echo 1;
    }
}

/**
 * 4. The other half of the pair above: the same two boolean operators, moved
 * into the middle clause, where they *are* counted — `1 + 2 + 1`. The two
 * callables carry the same operators and differ only in which clause holds
 * them, so a `for` that read all three clauses alike would score both 4, and one
 * that read none of them would score both 2. Only reading the middle clause
 * alone produces 2 here and 4 there.
 */
function forCountsItsConditionClause(bool $b, bool $c): void
{
    for ($i = 0; $i < 10 && ($b || $c); $i++) {
        echo 1;
    }
}

/**
 * 3: `B(cond)` of 1, the body, and 1 for the loop. A standalone `while` is
 * measured by the shared loop formula, not by the `do … while` one — doWhileLoop()
 * above only ever reaches the latter, so without this the `while` dispatch is
 * never exercised.
 */
function standaloneWhileLoop(int $a, bool $b): void
{
    while ($a > 0 && $b) {
        $a--;
    }
}

/**
 * 4. The short ternary `?:` has no `then` branch, and PDepend doubles its
 * condition in that branch's place: `(1 * 2) + 0 + 2`. The full-ternary formula
 * applied to the same expression would give 3, so the doubling is what this
 * number pins. Every other ternary in these fixtures is the full form.
 */
function shortTernaryDoublesItsCondition(bool $a, bool $b, int $c): int
{
    $value = ($a && $b) ?: $c;

    return $value;
}

/**
 * 5. `||`, `and`, and `or` each score 1, exactly as `&&` and `xor` do — the
 * three of the five boolean tokens that no other fixture counts live. Only their
 * text appears in uncountedConstructs(), inside a string and a heredoc, which
 * proves the opposite point. Three operators, plus the body and the missing
 * `else`, is 5.
 */
function logicalKeywordOperators(bool $a, bool $b, bool $c, bool $d): void
{
    if ($a || $b and $c or $d) {
        echo 1;
    }
}

/**
 * 3. `throw`, `yield`, and `continue` are scored by nothing of their own, so the
 * only thing counted here is the `foreach` around them: `B(expr) + 1 + N(body)`,
 * where the body holds an `if` worth 2. Giving any of the three a score of its
 * own would move this off 3.
 */
function throwYieldAndContinue(array $items): iterable
{
    foreach ($items as $item) {
        if ($item === null) {
            continue;
        }

        yield $item;
    }

    throw new \RuntimeException('exhausted');
}

/**
 * 2. A `match` adds nothing for itself and its arms are not a branch, but the
 * arm *bodies* are still ordinary expressions in the enclosing sequence — so a
 * ternary written in an arm multiplies into the callable exactly as it would
 * anywhere else. A live PHPMD run scores this 2, not 1.
 *
 * This is the arm shape uncountedConstructs() cannot express: its arms hold
 * nothing scoreable, so it cannot tell "the arms were skipped" from "there was
 * nothing in them to count".
 */
function matchArmTernaryMultiplies(int $a, bool $b): string
{
    $value = match ($a) {
        1 => $b ? 'x' : 'y',
        default => 'z',
    };

    return $value;
}

/**
 * 1, and the counterpart to the callable above. A boolean operator in an arm
 * body is *not* counted, because boolean operators are only summed inside a
 * construct's own condition or a `return`, never at statement level. So the two
 * arm shapes genuinely differ, and neither is "the match was skipped".
 */
function matchArmBooleanIsUncounted(int $a, bool $b, bool $c): bool
{
    $value = match ($a) {
        1 => $b && $c,
        default => false,
    };

    return $value;
}

/**
 * 5. PDepend reads a ternary's condition as the first *child node* of the
 * expression holding it, not as everything to the left of the `?`. Here that
 * first child is `$a`, so the `&&` belongs to the enclosing `if` condition alone
 * and is counted once: `B(cond)` is 1 for the operator plus 2 for the ternary,
 * and the `if` adds its body and the missing `else`.
 *
 * Counting the condition up to the `?` would count that `&&` twice and make this
 * 6 — which is what the callable below legitimately measures.
 */
function ternaryConditionStopsAtTheFirstOperator(bool $a, bool $b): int
{
    if ($a && $b ? 1 : 0) {
        return 1;
    }

    return 0;
}

/**
 * 6, and the only thing changed from the callable above is a pair of
 * parentheses. Now the first child node *is* the whole `($a && $b)` group, so
 * the operator is genuinely inside the ternary's condition as well as in the
 * enclosing walk, and PDepend counts it twice. A live PHPMD run reports 5 for
 * the callable above and 6 for this one.
 */
function ternaryConditionIncludesAParenthesisedGroup(bool $a, bool $b): int
{
    if (($a && $b) ? 1 : 0) {
        return 1;
    }

    return 0;
}

/**
 * 4. The `return` quirk, pinned at last: `return` multiplies its whole
 * expression's boolean complexity into the sequence, and the parenthesised group
 * is also the ternary's own condition, so the `&&` is counted once by each — 1
 * for the return expression plus 3 for the ternary.
 */
function returnTernaryCountsItsConditionTwice(bool $a, bool $b): int
{
    return ($a && $b) ? 1 : 2;
}

/**
 * 3, and the contrast that makes the quirk above visible: the identical ternary
 * assigned to a variable is counted once, because an assignment is not a
 * `return` and nothing sums its boolean operators a second time.
 */
function assignedTernaryCountsItsConditionOnce(bool $a, bool $b): int
{
    $value = ($a && $b) ? 1 : 2;

    return $value;
}

/**
 * 8, and the statement-position half of the pair below. PDepend reaches the
 * `if`s inside a closure only while it is walking *statements*, so a closure
 * held by an assignment contributes its whole body: three independent `if`s
 * multiply to 2 * 2 * 2.
 */
function closureInStatementPositionIsWalked(): callable
{
    $closure = function (int $b): int {
        if ($b === 1) {
            $b++;
        }

        if ($b === 2) {
            $b++;
        }

        if ($b === 3) {
            $b++;
        }

        return $b;
    };

    return $closure;
}

/**
 * 1, from the *identical* closure written in expression position — the contrast
 * that pins where PDepend's own boundary falls.
 *
 * A `return` is scored by `sumComplexity()`, which sums only boolean operators
 * and ternaries as it descends; it never runs the statement visitor that scores
 * an `if`. So the three `if`s above are worth nothing here, and a live PHPMD
 * 2.15.0 run reports exactly this: 8 for the callable above, 1 for this one.
 *
 * The pair is deliberately load-bearing. Teaching the expression walk to
 * descend into a closure body — the intuitive "fix" for the asymmetry — would
 * score this 8 as well and break parity with the tool this sniff replicates.
 */
function closureInExpressionPositionIsNotWalked(): callable
{
    return function (int $b): int {
        if ($b === 1) {
            $b++;
        }

        if ($b === 2) {
            $b++;
        }

        if ($b === 3) {
            $b++;
        }

        return $b;
    };
}

/**
 * 4, and the same boundary read from the other side: an arm's boolean operator
 * *is* counted once the `match` sits in an expression PDepend sums.
 *
 * matchArmBooleanIsUncounted() above measures 1 for the same arm at statement
 * level. Here `sumComplexity()` walks the whole `if` condition and reaches the
 * `&&` in the arm, so the condition is worth 2 rather than 1: a live PHPMD run
 * reports 4, not the 3 the statement-level rule alone would predict.
 */
function matchArmBooleanCountsInACondition(int $a, bool $b, bool $c): int
{
    if ($a > 0 && match ($a) {
        1 => $b && $c,
        default => false,
    }) {
        return 1;
    }

    return 0;
}

/**
 * 2, the `return` counterpart of the callable above: `return` sums its whole
 * expression, so the arm's `&&` counts there too. A live PHPMD run reports 2.
 */
function matchArmBooleanCountsInAReturn(int $a, bool $b, bool $c, bool $q): bool
{
    return $q && match ($a) {
        1 => $b && $c,
        default => false,
    };
}

/**
 * 3. A `switch` whose subject holds a `match` is the one shape PHPCS 3.13.6
 * builds no scope for — no `scope_opener`, no `scope_closer`, and labels whose
 * `conditions` skip the switch entirely.
 *
 * Read from the tokenizer alone this `switch` looks label-less, which scores 0
 * and would zero the whole callable. It is really `B(expr)` 1 for the `&&` in
 * the arm plus one range per label: a live PHPMD run reports 3.
 */
function switchWithAMatchSubject(int $a, bool $b, bool $c): int
{
    switch (match ($a) {
        1 => $b && $c,
        default => false,
    }) {
        case true:
            return 1;
        default:
            return 2;
    }
}

/**
 * 2, the boolean-free twin of the callable above. Without the `&&` the subject
 * is worth nothing, so the two labels alone carry the score — which is what
 * separates "the labels were found" from "the subject happened to score".
 * Measured 0 before the tokenizer gap was covered, and 0 is reported at no
 * threshold at all, so the callable vanished rather than reading low.
 */
function switchWithAMatchSubjectAndNoBoolean(int $a, bool $b): int
{
    switch (match ($a) {
        1 => $b,
        default => false,
    }) {
        case true:
            return 1;
        default:
            return 2;
    }
}

/**
 * 3, the same gap in the alternative syntax, which has no body brace to fall
 * back on and ends at its own `endswitch` instead.
 *
 * The tokenizer also truncates the *enclosing function's* scope here, landing
 * its `scope_closer` on the `endswitch` rather than on the function's own
 * brace, so the `return` below the switch is hidden along with the labels. The
 * trailing statement is here to hold that second half of the gap: it is inside
 * the callable PDepend measures, and a live PHPMD run reports 3.
 */
function alternativeSyntaxSwitchWithAMatchSubject(int $a, bool $b, bool $c): int
{
    switch (match ($a) {
        1 => $b && $c,
        default => false,
    }):
        case true:
            return 1;
        default:
            return 2;
    endswitch;

    return 0;
}

/**
 * 4. A braceless body is one PHPCS builds no scope for, and the seven callables
 * below are the whole of that shape: every construct that can own one, each
 * wrapping a body that carries its own NPath.
 *
 * This one is the `do` of `do <statement> while (…);`, wrapping a loop. Read
 * off the scope pointers the body looks like nothing at all, and a body worth
 * nothing scores the 1 an empty sequence scores — which left the loop's own 3
 * to be found again as a statement of the enclosing block and multiplied in
 * rather than added, measuring 6.
 */
function bracelessDoWrappingALoop(int $a, bool $b, bool $c): void
{
    do
        while ($b && $c) {
            $a--;
        }
    while ($a > 0);
}

/**
 * 3, the same shape under an `if`. Measured 4 before the braceless body was
 * dispatched as a statement.
 */
function bracelessIfWrappingALoop(bool $x, int $a): void
{
    if ($x) while ($a > 0) { $a--; }
}

/**
 * 3, the same shape under a `while`.
 */
function bracelessWhileWrappingAnIf(int $a, bool $b): void
{
    while ($a > 0) if ($b) { $a--; }
}

/**
 * 3, the same shape under a `for`. `for` and `foreach` reach the braceless body
 * through the same code path as `while`, so both are held here rather than
 * assumed from it.
 */
function bracelessForWrappingAnIf(bool $b): void
{
    for ($i = 0; $i < 10; $i++) if ($b) { echo $i; }
}

/**
 * 3, the same shape under a `foreach`.
 */
function bracelessForeachWrappingAnIf(array $items, bool $b): void
{
    foreach ($items as $item) if ($b) { echo $item; }
}

/**
 * 3, the same shape under an `else`, which reaches its body from the keyword
 * rather than from a closing parenthesis it does not have.
 */
function bracelessElseWrappingALoop(bool $x, int $a): void
{
    if ($x) {
        echo 1;
    } else while ($a > 0) { $a--; }
}

/**
 * 3, a braceless body that is itself braceless, so the dispatch recurses.
 */
function bracelessIfWrappingABracelessIf(bool $x, bool $y): void
{
    if ($x) if ($y) echo 1;
}

/**
 * 3. A `switch` nested in another `switch`'s case body, carrying the `match`
 * subject that leaves the inner one with no scope of its own.
 *
 * The inner labels belong to the inner construct, so reading them as ranges of
 * the outer one would measure the outer high. Nothing else in this file nests
 * the two, and the scope-less subject is the harder half: the label walk cannot
 * lean on the inner construct's own scope to know where it ends.
 */
function nestedSwitchInACaseBody(int $a, int $b, bool $c): int
{
    switch ($a) {
        case 1:
            switch (match ($b) {
                1 => $c,
                default => false,
            }) {
                case true:
                    return 1;
                default:
                    return 2;
            }
        default:
            return 3;
    }
}
