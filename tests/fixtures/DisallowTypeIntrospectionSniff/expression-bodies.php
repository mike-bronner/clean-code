<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

/**
 * Braces sitting inside an expression, from both sides of the group rule.
 *
 * A scan that decides which branch a check is measured against crosses a
 * balanced group whole — the check before a call's parentheses and the check
 * after them read the same expression. Braces are a group on exactly the same
 * terms: `new class { … }`, `function () { … }` and `match (…) { … }` are each
 * written where a value is expected, so the expression carries on after the
 * closing brace. A scan that stopped at one lost every branch written past it.
 *
 * The operands below are deliberately the smallest thing that puts a braced
 * body in each position. Nobody writes `new class { … } instanceof Failure` by
 * choice, but the sniff has to read it the same way it reads `$value
 * instanceof Failure`, and each of these three positions broke differently
 * before it did.
 *
 * The silent direction is the other half: a statement block's braces are not a
 * group, they are where the expression ended. Crossing one would read the
 * statements *after* an `if` or a `foreach` as a continuation of the expression
 * before it, and report a check that decides nothing.
 */
final class BracedOperand
{
    /**
     * A `match` arm's condition. The arm boundary a check is measured against
     * is the `=>` before it, and the anonymous class holds a `match` of its
     * own: a scan crossing into the body reads that inner arrow as the boundary
     * and reads the check as an arm *result*, which decides nothing.
     */
    public function matchArm(object $value): int
    {
        return match (true) {
            new class {
                public function label(int $size): string
                {
                    return match (true) {
                        $size > 1 => 'many',
                        default => 'one'
                    };
                }
            } instanceof Failure, $value instanceof Failure => 1,
            default => 0,
        };
    }

    /**
     * A `switch` case label. The label a check is measured against is the
     * `case` before it, and a closing brace used to end that scan outright —
     * so the anonymous class's own body hid the `case` from every check
     * written after it.
     */
    public function switchCase(object $value): int
    {
        switch (true) {
            case new class {
                public function label(): bool
                {
                    return true;
                }
            } instanceof Failure:
                return 1;

            default:
                return 0;
        }
    }

    /**
     * A ternary condition, with the braced body between the check and the `?`.
     * All three body forms, one per method, because each is a different scope
     * owner and the tokenizer links them differently.
     *
     * The check *after* the body is reported either way — it is the one before
     * it, whose scan has to cross the body to reach the `?`, that the group
     * rule decides.
     */
    public function ternaryPastAnonymousClass(object $value): string
    {
        return $value instanceof Failure && new class {
            public function flag(): bool
            {
                return true;
            }
        } instanceof Failure ? 'failure' : 'ok';
    }

    public function ternaryPastClosure(object $value): string
    {
        return $value instanceof Failure && function () {
            return true;
        } instanceof Failure ? 'failure' : 'ok';
    }

    public function ternaryPastMatch(object $value, string $kind): string
    {
        return $value instanceof Failure && match ($kind) {
            'failure' => $value,
            default => $value,
        } instanceof Failure ? 'failure' : 'ok';
    }

    /**
     * Braces owning no scope at all. The tokenizer links `${…}` with the same
     * bracket pointers and nothing else, and it ends no expression either.
     */
    public function ternaryPastVariableVariable(object $value, string $name): string
    {
        return $value instanceof Failure && ${$name} instanceof Failure ? 'failure' : 'ok';
    }

    /**
     * The silent direction. `foreach` decides no branch on the value in its
     * parentheses — it iterates whatever it is given — so the check inside its
     * header reports a type and nothing more. The `?` further down belongs to a
     * later statement, and the loop's own braces are what separate the two.
     */
    public function statementBlockEndsTheExpression(array $items, object $value, bool $flag): string
    {
        foreach (array_keys([$value instanceof Failure]) as $item) {
            unset($item);
        }

        return $flag ? 'failure' : 'ok';
    }

    /**
     * The same rule read backwards, from a case *body*: a predicate written
     * after a block inside the body is not a case label, and stays silent.
     *
     * The block's braces are not a group here either, and the rule is written
     * once for both directions rather than once per direction — but the label's
     * own `:` already ends this scan whether the braces are crossed or not, so
     * this pins the silence, not the brace rule. No backward scan can reach a
     * `case` past a statement block: the `:` between a label and its body is
     * always in the way.
     */
    public function statementBlockInACaseBody(object $value, bool $flag): string
    {
        switch ($flag) {
            case true:
                if ($flag) {
                    unset($flag);
                }

                return $this->report($value instanceof Failure);

            default:
                return 'ok';
        }
    }

    private function report(bool $isFailure): string
    {
        return $isFailure ? 'failure' : 'ok';
    }
}

/**
 * The type the checks above name. Declared here so the fixture parses as
 * ordinary code rather than leaning on an undefined symbol.
 */
final class Failure
{
}
