<?php

declare(strict_types=1);

namespace App\Fixtures;

// Nothing here reaches the default maximum of 50, so this file must produce
// zero violations.
//
// AtOneBelowTheMaximum sits one under it — the near miss the threshold has to
// stay silent on — and UncountedConstructs is stuffed with the constructs
// PDepend deliberately does not score, so a sniff that counted any of them
// would push it over on its own.
//
// The small classes at the end of the file are small on purpose. Their job is
// not the threshold but the measurement: each pins a counting rule the classes
// above never reach, so the exact-count assertion in
// tests/Standards/ExcessiveClassComplexityTest.php moves if that rule breaks.

class AtOneBelowTheMaximum
{
    /**
     * 13: 1 for the method, plus 12 decision points — if, &&, foreach, the
     * ternary, elseif, ||, for, while, the `while` of the do-while, two cases,
     * and the catch. The `else`, the `default`, and the `finally` add nothing,
     * matching PDepend.
     */
    public function varied(int $a, int $b, array $items): int
    {
        if ($a && $b) {
            foreach ($items as $item) {
                $a += $item ? 1 : 0;
            }
        } elseif ($a || $b) {
            for ($i = 0; $i < $b; $i++) {
                $a--;
            }
        } else {
            while ($a > 0) {
                $a--;
            }
        }

        do {
            $b--;
        } while ($b > 0);

        try {
            switch ($a) {
                case 1:
                    break;
                case 2:
                    break;
                default:
                    break;
            }
        } catch (\Throwable) {
            $a = 0;
        } finally {
            $b = 0;
        }

        return $a + $b;
    }

    /**
     * Pads the class to a weighted method count of exactly 49: 13 above plus 1 for this method and 35 boolean operators.
     */
    public function padding(bool $flag): bool
    {
        return $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag;
    }
}

class UncountedConstructs
{
    /**
     * Every construct below is one PDepend does not count. There are far more
     * than 50 of them, so this method is worth 1 and this class is worth 4 —
     * the other 3 come from elseOnly() below.
     */
    public function uncounted(?int $a, ?object $o): int
    {
        $b = $a ?? 1;
        $b ??= 2;
        $c = $a ?? $b ?? 1 ?? 2 ?? 3 ?? 4 ?? 5 ?? 6 ?? 7 ?? 8 ?? 9 ?? 10;
        $d = $a ?? $b ?? 1 ?? 2 ?? 3 ?? 4 ?? 5 ?? 6 ?? 7 ?? 8 ?? 9 ?? 10;
        $e = $a xor $b;
        $e = ($a xor $b) xor true;
        $o?->one?->two?->three?->four?->five;

        $f = match ($b) {
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            11 => 11,
            12 => 12,
            13 => 13,
            14 => 14,
            15 => 15,
            16 => 16,
            17 => 17,
            18 => 18,
            19 => 19,
            20 => 20,
            default => 0,
        };

        switch ($f) {
            default:
                $f = 0;
                break;
        }

        try {
            $f++;
        } finally {
            $f--;
        }

        goto done;

        done:

        return $b + $c + $d + (int) $e + $f;
    }

    /**
     * The `else` half of a chain, over and over. Only the two `if`s score, so
     * this method is worth 3.
     */
    public function elseOnly(int $a): int
    {
        if ($a > 0) {
            $a++;
        } else {
            $a--;
        }

        if ($a > 0) {
            $a++;
        } else {
            $a--;
        }

        return $a;
    }
}

abstract class AbstractMethods
{
    /**
     * PDepend scores a method with no body 1, the same as a concrete one with
     * an empty body, so this class is worth 4 in both tools.
     */
    abstract public function first(): void;

    abstract public function second(): void;

    abstract public function third(): void;

    public function fourth(): void
    {
    }
}

class KeywordBooleanOperators
{
    /**
     * `and` and `or` — the keyword spellings — score exactly as `&&` and `||`
     * do, which no other fixture in this suite exercises: every boolean count
     * elsewhere is written in the symbol form.
     *
     * 8: 1 for the method, plus 7 decision points — two `if`s, two `and`s, and
     * three `or`s. `xor`, the third keyword operator, is worth nothing and is
     * pinned by UncountedConstructs above.
     *
     * Measured at 8 by a live PHPMD 2.15.0 run over this file, not derived from
     * the sniff.
     */
    public function keywordOperators(bool $a, bool $b): bool
    {
        if ($a and $b) {
            return true;
        }

        if ($a or $b) {
            return false;
        }

        return ($a and $b) or ($a or $b);
    }
}

class InlineFunctionBodies
{
    /**
     * A closure and an arrow function are not artifacts of their own for this
     * metric: PDepend walks the enclosing method's whole subtree, so their
     * decision points are the method's. A sniff that skipped over either — the
     * plausible-looking edit, given a named function and an anonymous class
     * both *are* skipped — would measure this class 2 or 1 instead of 3.
     *
     * 3: 1 for the method, plus the `&&` inside the closure and the `?` of the
     * arrow function's ternary. Neither the `function` keyword nor the `fn`
     * keyword adds anything itself.
     *
     * Measured at 3 by a live PHPMD 2.15.0 run over this file, not derived from
     * the sniff.
     */
    public function inlineFunctions(array $items): array
    {
        $keep = function (int $item): bool {
            return $item > 0 && $item < 10;
        };

        $double = fn (int $item): int => $item > 0 ? $item * 2 : 0;

        return array_map($double, array_filter($items, $keep));
    }
}

class AnonymousClassArguments
{
    /**
     * An anonymous class's constructor arguments are ordinary expressions in
     * the method that writes them: PHP evaluates them there, and PDepend scores
     * them there. Only the class's *body* is the separate artifact. A sniff
     * that skipped from the `class` keyword instead of from the opening brace
     * would swallow the argument list with the body and measure this method 1.
     *
     * 3: 1 for the method, plus the ternary and the `&&` in the argument list.
     * The anonymous class's own body still adds nothing — neither its property
     * default nor its measure() ternary is counted, which is what separates
     * "skip the body" from "skip the whole expression".
     */
    public function make(bool $flag): object
    {
        return new class ($flag ? 1 : 0, $flag && $flag) {
            public bool $flags = true && true;

            public function __construct(public int $value, public bool $both)
            {
            }

            public function measure(bool $other): int
            {
                return $other ? 1 : 0;
            }
        };
    }

    /**
     * The same rule one level down. The ternary here sits in the *inner*
     * anonymous class's argument list, which sits inside the outer one's — so
     * it is reached only if the skip nests rather than jumping over the whole
     * outer expression at the first `class` keyword.
     *
     * 2: 1 for the method plus that ternary.
     */
    public function nest(bool $flag): object
    {
        return new class (new class ($flag ? 1 : 0) {
            public function __construct(public int $inner)
            {
            }
        }) {
            public function __construct(public object $outer)
            {
            }
        };
    }
}

class MemberDefaults
{
    /**
     * Only method bodies are measured. A decision operator in a property
     * default, a constant default, or a parameter default sits outside every
     * method body, and neither tool counts it.
     *
     * Two separate guarantees hold that up, and each has its own way of
     * breaking: walking a method from its `function` keyword rather than from
     * its opening brace would count the parameter default, and scanning the
     * class body outside its methods would count the property and constant
     * defaults. Either raises this class above 3.
     *
     * 3: 1 for the constructor, and 2 for measure() — itself plus its ternary.
     *
     * The anonymous class in not-a-class.php exercises a property default too,
     * but behind the anonymous-class body skip; this class reaches the same
     * guarantee for a plain named class, where nothing but the walk's own
     * starting point keeps the defaults out.
     */
    public const YES = true;

    public const NO = false;

    public const EITHER = self::YES || self::NO;

    public bool $enabled = self::YES && self::YES;

    public function __construct(private int $limit = self::YES ? 1 : 0)
    {
    }

    public function measure(bool $flag): int
    {
        return $flag ? $this->limit : 0;
    }
}

class BareBlocks
{
    /**
     * The anonymous-class skip keys on an opening brace, so every other brace
     * has to fall through it. A bare block is the one that owns nothing at all:
     * PHPCS records neither a scope condition nor a scope closer for it.
     *
     * 2: 1 for the method plus the `if` inside the block.
     *
     * This class is here because nothing else in the suite reaches that
     * ownerless branch, not because a mutation of it reddens — none does, and
     * the reason is worth stating: a brace with no scope closer has nothing to
     * skip to, so the walk carries on through the block whichever way the
     * branch returns. The branch keeps the token lookup from raising an
     * undefined-key warning; the count below is what this fixture pins.
     */
    public function bare(int $a): int
    {
        {
            if ($a > 0) {
                $a++;
            }
        }

        return $a;
    }
}
