<?php

declare(strict_types=1);

/**
 * Every declaration here measures below the default report level of 10, and
 * each one pins one counting rule at an exact number. The exact numbers are
 * asserted in tests/Standards/CyclomaticComplexityTest.php, not merely the
 * silence: no single construct appears ten times, so mis-counting one of them
 * would move a measurement without pushing it over the level, and a silence
 * assertion alone would not notice.
 *
 * The numbers were read off a live PHPMD 2.15.0 run over this file, quoted
 * verbatim in the test file's docblock.
 */

interface Contract
{
    // No body, so no decision points to count: 1 in both tools.
    public function describe(): string;
}

abstract class Baselines
{
    // 1 — the base every declaration carries, and nothing else.
    public function baseline(): int
    {
        return 1;
    }

    // 1 — an abstract method has no body at all.
    abstract public function drawn(): int;

    // 9 — one below the default report level. The near miss the boundary has
    // to stay silent on, built from eight ifs plus the base.
    public function atOneBelowTheReportLevel(int $n): int
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

        return 0;
    }

    // 2 — the paired control for the boolean-operator method in failing.php.
    // The same single if, with the ten-operator && chain taken out of it.
    public function booleanChainRemoved(bool $a): bool
    {
        if ($a) {
            return true;
        }

        return false;
    }
}

class UncountedConstructs
{
    // 2 — the `if` guarding the goto is the only construct in this body that
    // scores at all. The `??`, `??=`, `?->`, `xor`, four-armed `match`,
    // `finally`, and `goto` are worth nothing between them, so counting any
    // one of those kinds raises this number.
    public function uncounted(?object $o, ?int $n, int $m, bool $a, bool $b): mixed
    {
        $x = $n ?? 1;
        $x ??= 2;
        $y = $o?->thing;
        $z = $a xor $b;

        $w = match ($m) {
            1 => 'one',
            2 => 'two',
            3 => 'three',
            default => 'other',
        };

        try {
            $v = $x;
        } finally {
            $v = $y;
        }

        if ($m > 100) {
            goto done;
        }

        done:

        return [$w, $v, $z];
    }
}

class Uncounted2
{
    // 2 — the `else` half on its own: one if, one else, one bare default in a
    // switch that has no case at all.
    public function elseAndDefaultOnly(int $n): string
    {
        if ($n > 0) {
            $label = 'positive';
        } else {
            $label = 'other';
        }

        switch ($n) {
            default:
                return $label;
        }
    }
}

class MatchArms
{
    // 3 — a match scores nothing, but the && in its first arm and the ternary
    // in its second are ordinary decision points and each score 1. A sniff
    // that skipped everything nested under `match` rather than the `match`
    // token alone reports 1 here.
    public function nestedInsideArms(int $m, bool $a, bool $b): mixed
    {
        return match ($m) {
            1 => $a && $b,
            2 => $a ? 'yes' : 'no',
            default => null,
        };
    }
}

class Switches
{
    // 3 — 1 plus two cases. The `default` in the same switch scores nothing,
    // so counting it would make this 4.
    public function withDefault(int $n): string
    {
        switch ($n) {
            case 1:
                return 'a';
            case 2:
                return 'b';
            default:
                return 'c';
        }
    }

    // 4 — 1 plus three case labels. Two of them share one body, and each still
    // scores: a sniff counting bodies rather than labels reports 3.
    public function stackedLabels(int $n): string
    {
        switch ($n) {
            case 1:
            case 2:
                return 'a';
            case 3:
                return 'b';
        }

        return 'c';
    }
}

class Operators
{
    // 3 — the keyword spellings `and` and `or` score 1 each, and `xor` scores
    // nothing. PDepend has visitLogicalAndExpression and
    // visitLogicalOrExpression and no visitor for xor, which is why the third
    // operator here is the one that must not move the number.
    public function wordForms(bool $a, bool $b, bool $c, bool $d): bool
    {
        return (($a and $b) or $c) xor $d;
    }

    // 4 — one full ternary and two short ones, worth 1 each. The short form
    // is the same decision point as the full one, which is why both spellings
    // appear here.
    public function ternaries(?int $n, ?int $m): int
    {
        $first = $n === null ? 0 : $n;

        return $first ?: ($m ?: 0);
    }
}

class Flow
{
    // 7 — 1 plus for, foreach, while, the while of a do-while, and two
    // catches. The do-while is worth exactly 1: counting `do` as well as its
    // `while` would make this 8.
    public function everyLoopAndCatch(array $rows): int
    {
        $total = 0;

        for ($i = 0; $i < 3; $i++) {
            $total++;
        }

        foreach ($rows as $row) {
            $total += $row;
        }

        while ($total > 100) {
            $total--;
        }

        do {
            $total++;
        } while ($total < 0);

        try {
            $total++;
        } catch (RuntimeException | LogicException $e) {
            $total = 0;
        } catch (Throwable $e) {
            $total = -1;
        }

        return $total;
    }

    // 4 — 1 plus an if, an `else if`, and an `elseif`. The two spellings are
    // worth the same, and the trailing `else` is worth nothing.
    public function elseIfSpellings(int $n): int
    {
        if ($n === 1) {
            return 1;
        } else if ($n === 2) {
            return 2;
        } elseif ($n === 3) {
            return 3;
        } else {
            return 4;
        }
    }
}

class InlineFunctions
{
    // 5 — the method's own if, plus the closure's if and &&, plus the arrow
    // function's ternary. PDepend walks the whole subtree of a method, so a
    // closure written inside one is scored against it and is never reported
    // under its own name. Splitting either declaration out drops this number.
    public function hostsInlineFunctions(array $rows): callable
    {
        if ($rows === []) {
            return static fn (int $n): int => $n > 0 ? 1 : 0;
        }

        return static function (int $n) use ($rows): int {
            if ($n > 0 && $rows !== []) {
                return 1;
            }

            return 0;
        };
    }
}

class NestedNamedFunction
{
    // 2 for the method, 9 for nested() — a named function declared inside a
    // method is its own artifact in PDepend, so its eight ifs never reach the
    // method holding it, and it is measured and reported in its own right.
    public function hostsNamedFunction(bool $a): int
    {
        if ($a) {
            return 1;
        }

        function nested(int $n): int
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

            return 0;
        }

        return 0;
    }
}

class AnonymousClassHost
{
    // 3 for the method — the ternary and the && in the anonymous class's
    // constructor arguments are ordinary expressions in this method, and PHP
    // evaluates them here. The anonymous class's own body is not: inner()
    // measures 3 in its own right. Skipping the anonymous class from its
    // `class` keyword rather than from its brace swallows the arguments and
    // drops the method to 1; not skipping its body at all raises it to 5.
    public function makes(bool $a, bool $b): object
    {
        return new class ($a ? 1 : 0, $a && $b) {
            public function __construct(private int $n, private bool $flag)
            {
            }

            // 3 — 1 plus an if and an &&.
            public function inner(bool $c): int
            {
                if ($c && $this->flag) {
                    return $this->n;
                }

                return 0;
            }
        };
    }
}

class MemberDefaults
{
    public const LIMIT = true ? 1 : 2;

    private int $size = true ? 3 : 4;

    // 1 — the ternary in the parameter default sits outside the body, and
    // scores in neither tool. Measuring from the `function` keyword rather
    // than from the opening brace would make this 2.
    public function measure(int $n = true ? 5 : 6): int
    {
        return $n + $this->size + self::LIMIT;
    }
}

class BareBlock
{
    // 2 — an if inside a bare block. A bare block's brace owns nothing and
    // carries no scope closer, which is the one shape the walk's skip test has
    // to survive rather than measure.
    public function withBareBlock(bool $a): int
    {
        {
            if ($a) {
                return 1;
            }
        }

        return 0;
    }
}

trait MeasuredTrait
{
    // 2 — a trait's methods are measured and reported like any other.
    public function fromTrait(bool $a): int
    {
        return $a ? 1 : 0;
    }
}

enum MeasuredEnum: string
{
    case A = 'a';

    // 2 — an enum's methods are measured and reported like any other.
    public function label(bool $a): string
    {
        return $a ? 'yes' : 'no';
    }
}

// 3 — a standalone function is reported in its own right, as PHPMD's
// FunctionAware half does.
function standalone(bool $a, bool $b): bool
{
    if ($a || $b) {
        return true;
    }

    return false;
}

/*
 * A closure and an arrow function written at file scope, each carrying twelve
 * decision points. Neither is a named function or a method, so PHPMD never
 * reports either one however complex it is, and neither does this sniff —
 * registering on T_CLOSURE or T_FN would report both here.
 */
$closure = static function (int $n, bool $a, bool $b): int {
    if ($n === 1 && $a) {
        return 1;
    }

    if ($n === 2 && $b) {
        return 2;
    }

    if ($n === 3 && $a) {
        return 3;
    }

    if ($n === 4 && $b) {
        return 4;
    }

    if ($n === 5 && $a) {
        return 5;
    }

    if ($n === 6 && $b) {
        return 6;
    }

    return 0;
};

$arrow = static fn (int $n, bool $a, bool $b): int => $n === 1 && $a
    ? 1
    : ($n === 2 && $b
        ? 2
        : ($n === 3 && $a
            ? 3
            : ($n === 4 && $b ? 4 : 0)));
