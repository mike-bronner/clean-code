<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Conditionals.TypeDiscriminatorDispatch.
 *
 * The compliant form is polymorphism, and it opens the file. Every method after
 * it is a *near miss*: a dispatch that reaches the branch count and looks
 * discriminator-shaped, but breaks exactly one of the sniff's rules. Each one
 * pins a different exclusion, so relaxing any single rule reddens this file:
 *
 *   - plainVariableSwitch     a bare local as the switch subject
 *   - plainVariableIf         the same, in the if form
 *   - classConstantLabel      a class constant as one `case` label
 *   - bareConstantLabel       a bare constant as one `case` label
 *   - variableLabel           a variable as one `case` label
 *   - nonLiteralOperandIf     a variable as an if condition's other operand
 *   - classConstantOperandIf  a class constant in the same position
 *   - bareConstantOperandIf   a bare constant in the same position
 *   - reversedNonLiteralOperandIf
 *                             the same, with the operands written the other
 *                             way round
 *   - compoundCondition       a compound boolean condition
 *   - instanceofCondition     `instanceof` rather than a literal comparison
 *   - rangeCondition          `>`, which is not one of the two comparisons
 *   - notIdenticalCondition   `!==`, the negation, which enumerates nothing
 *   - calledDiscriminator     a call rather than a property read
 *   - parenthesisedCondition  an extra parenthesis pair — not three tokens
 *   - twoCaseSwitch           below the default minimum
 *   - stackedPairSwitch       two stacked labels and nothing else — still two
 *   - twoBranchIf             below the default minimum, in the if form
 *   - matchExpression         a `match` of exactly the qualifying shape
 *   - crossVariableIf         the same property name on two different variables
 *   - switchOnTrue            `switch (true)`, whose subject is a literal
 *   - deepIndexSwitch         an index read two hops deep
 *   - deepIndexIf             the same, in the if form
 *   - deepPropertySwitch      a property read two hops deep
 *   - deepPropertyIf          the same, in the if form
 *   - positionalIndexSwitch   a positional index rather than a named field
 *   - staticPropertySwitch    a static read, which belongs to the class
 *   - adjacentBracedIfs       two braced constructs that only sit side by side
 *   - adjacentBracelessIfs    the same adjacency, with brace-less bodies
 *   - nestedBracelessIf       a nested `if`, which the continuations bind to
 *   - nestedBracedIf          the same nesting, with the nested clauses braced
 *   - nestedLoopIf            the same nesting, one brace-less loop further in
 *   - nestedTwoLoopsIf        the same nesting, two brace-less loops in
 *   - bracelessLoopMismatch   a clause the reported body span runs past, whose
 *                             discriminator disqualifies the whole chain
 */

interface Shape
{
    public function area(): float;
}

final class NearMisses
{
    public const CIRCLE = 'circle';

    public static string $kind = 'circle';

    public function compliant(Shape $shape): float
    {
        return $shape->area();
    }

    public function plainVariableSwitch(string $type): string
    {
        switch ($type) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function plainVariableIf(string $type): string
    {
        if ($type === 'circle') {
            return 'Circle';
        } elseif ($type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function classConstantLabel(object $shape): string
    {
        switch ($shape->type) {
            case self::CIRCLE:
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function bareConstantLabel(object $shape): string
    {
        switch ($shape->type) {
            case CIRCLE_TYPE:
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function variableLabel(object $shape, string $circle): string
    {
        switch ($shape->type) {
            case $circle:
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }
    /**
     * The if form of the three case-label near-misses above. A `case` label has
     * one operand to be a literal; an `if` condition has two, so the same rule
     * is written at two token positions and needs pinning at both. This method
     * and the two after it put a non-literal on the right of the comparison;
     * reversedNonLiteralOperandIf puts one on the left.
     *
     * A variable first, the counterpart of variableLabel. Every other rule is
     * met — one discriminator read, three branches, `===` throughout — so the
     * literal check on the condition's other operand is the only thing between
     * this chain and a report.
     */
    public function nonLiteralOperandIf(object $shape, string $circle): string
    {
        if ($shape->type === $circle) {
            return 'Circle';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } elseif ($shape->type === 'rect') {
            return 'Rect';
        }

        return 'Unknown';
    }

    /**
     * A class constant, the counterpart of classConstantLabel. It names a
     * variant no more than a variable does: the value behind it lives in
     * another line of another file, and nothing here reads it.
     */
    public function classConstantOperandIf(object $shape): string
    {
        if ($shape->type === self::CIRCLE) {
            return 'Circle';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } elseif ($shape->type === 'rect') {
            return 'Rect';
        }

        return 'Unknown';
    }

    /**
     * A bare constant, the counterpart of bareConstantLabel — the same
     * indirection without the class in front of it.
     */
    public function bareConstantOperandIf(object $shape): string
    {
        if ($shape->type === CIRCLE_TYPE) {
            return 'Circle';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } elseif ($shape->type === 'rect') {
            return 'Rect';
        }

        return 'Unknown';
    }

    /**
     * The same non-literal operand written on the *left*, which the condition
     * read reaches through a second literal check — the one that runs after the
     * discriminator is found on the right. The three methods above cannot pin
     * it: their discriminator is on the left, so the read answers before that
     * check is consulted. failing.php proves this operand order is admitted
     * when the other operand really is a literal; this proves it is refused
     * when it is not.
     */
    public function reversedNonLiteralOperandIf(object $shape, string $circle): string
    {
        if ($circle === $shape->type) {
            return 'Circle';
        } elseif ('square' === $shape->type) {
            return 'Square';
        } elseif ('rect' === $shape->type) {
            return 'Rect';
        }

        return 'Unknown';
    }

    public function compoundCondition(object $shape, bool $scaled): string
    {
        if ($shape->type === 'circle' && $scaled === true) {
            return 'Circle';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function instanceofCondition(object $shape): string
    {
        if ($shape instanceof Shape) {
            return 'Shape';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function rangeCondition(object $shape): string
    {
        if ($shape->sides > 4) {
            return 'Polygon';
        } elseif ($shape->sides === 3) {
            return 'Triangle';
        } else {
            return 'Unknown';
        }
    }

    public function notIdenticalCondition(object $shape): string
    {
        if ($shape->type !== 'circle') {
            return 'Other';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function calledDiscriminator(object $shape): string
    {
        if ($shape->type() === 'circle') {
            return 'Circle';
        } elseif ($shape->type() === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function parenthesisedCondition(object $shape): string
    {
        if (($shape->type === 'circle')) {
            return 'Circle';
        } elseif (($shape->type === 'square')) {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function twoCaseSwitch(object $shape): string
    {
        switch ($shape->type) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
        }

        return 'Unknown';
    }

    public function stackedPairSwitch(object $shape): string
    {
        switch ($shape->type) {
            case 'circle':
            case 'ellipse':
                return 'Round';
        }

        return 'Unknown';
    }

    public function twoBranchIf(object $shape): string
    {
        if ($shape->type === 'circle') {
            return 'Circle';
        } else {
            return 'Unknown';
        }
    }

    public function matchExpression(object $shape): string
    {
        return match ($shape->type) {
            'circle' => 'Circle',
            'square' => 'Square',
            default => 'Unknown',
        };
    }

    public function crossVariableIf(object $shape, object $model): string
    {
        if ($shape->type === 'circle') {
            return 'Circle';
        } elseif ($model->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function switchOnTrue(object $shape): string
    {
        switch (true) {
            case $shape->type === 'circle':
                return 'Circle';
            case $shape->type === 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    /**
     * @param array<string, array<string, string>> $row
     */
    public function deepIndexSwitch(array $row): string
    {
        switch ($row['meta']['type']) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    /**
     * @param array<string, array<string, string>> $row
     */
    public function deepIndexIf(array $row): string
    {
        if ($row['meta']['type'] === 'circle') {
            return 'Circle';
        } elseif ($row['meta']['type'] === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    public function deepPropertySwitch(object $shape): string
    {
        switch ($shape->meta->type) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function deepPropertyIf(object $shape): string
    {
        if ($shape->meta->type === 'circle') {
            return 'Circle';
        } elseif ($shape->meta->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    /**
     * @param array<int, string> $row
     */
    public function positionalIndexSwitch(array $row): string
    {
        switch ($row[0]) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function staticPropertySwitch(): string
    {
        switch (self::$kind) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    /**
     * Two separate constructs, one branch and then two, neither reaching the
     * minimum on its own. Only reading the second `if` as a continuation of the
     * first — which nothing in the source says it is — adds them up to three.
     */
    public function adjacentBracedIfs(object $shape): string
    {
        if ($shape->type === 'circle') {
            return 'Circle';
        }

        if ($shape->type === 'square') {
            return 'Square';
        } elseif ($shape->type === 'rect') {
            return 'Rect';
        }

        return 'Unknown';
    }

    /**
     * The same adjacency where no clause has a body to close: three one-branch
     * constructs in a row, whose bodies end at their semicolons rather than at a
     * brace. It is a second path through the clause walk, so it is a second way
     * to read three unrelated statements as one chain.
     */
    public function adjacentBracelessIfs(object $shape): string
    {
        if ($shape->type === 'circle') return 'Circle';
        if ($shape->type === 'square') return 'Square';
        if ($shape->type === 'rect') return 'Rect';

        return 'Unknown';
    }

    /**
     * One `if` on a discriminator whose whole body is another `if`. PHP binds
     * both continuations below to the *inner* `if ($flag)`, so the outer one has
     * a single branch and dispatches on nothing. Reading them as the outer's
     * reports a three-branch chain that PHP never runs as one.
     */
    public function nestedBracelessIf(object $shape, bool $flag): string
    {
        if ($shape->type === 'circle')
            if ($flag)
                return 'Round';
            elseif ($shape->type === 'square')
                return 'Square';
            elseif ($shape->type === 'rect')
                return 'Rect';

        return 'Unknown';
    }

    /**
     * The same binding where the nested clauses are braced. The braces close
     * each clause's body, not the chain, so the `elseif`s still belong to the
     * inner `if`. It is the nested `if` carrying a scope of its own that makes
     * this a second case: the body walk skips whole any scope written inside the
     * body, so only reading the `if` *before* that skip finds this one.
     */
    public function nestedBracedIf(object $shape, bool $flag): string
    {
        if ($shape->type === 'circle')
            if ($flag) {
                return 'Round';
            } elseif ($shape->type === 'square') {
                return 'Square';
            } elseif ($shape->type === 'rect') {
                return 'Rect';
            }

        return 'Unknown';
    }

    /**
     * The nested `if` one statement further in, behind a brace-less `foreach`.
     * The outer clause's body is the whole loop, and the `if` that takes the
     * continuations is the loop's body rather than the clause's — so finding it
     * means reading the body through, not just looking at the token it opens on.
     *
     * @param array<int, int> $sides
     */
    public function nestedLoopIf(object $shape, array $sides, bool $flag): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side)
                if ($flag)
                    return 'Round';
                elseif ($shape->type === 'square')
                    return 'Square';
                elseif ($shape->type === 'rect')
                    return 'Rect';

        return 'Unknown';
    }

    /**
     * The nested `if` two brace-less loops in rather than one. The boundary a
     * body span is validated against is whichever comes first in that body, at
     * whatever depth it is written, so nesting the `if` deeper does not hide it
     * and these continuations still bind inward.
     *
     * @param array<int, int> $sides
     * @param array<int, int> $rows
     */
    public function nestedTwoLoopsIf(object $shape, array $sides, array $rows, bool $flag): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side)
                foreach ($rows as $row)
                    if ($flag)
                        return 'Round';
                    elseif ($shape->type === 'square')
                        return 'Square';
                    elseif ($shape->type === 'rect')
                        return 'Rect';

        return 'Unknown';
    }

    /**
     * The overrunning body span's other face. The clause the span runs past
     * reads a *different* discriminator, so it disqualifies the whole chain —
     * and only a walk that visits the clause can find that out. Swallowed into
     * the body it is never compared, and the three clauses that do share
     * `$shape->type` get reported as a chain PHP never runs as one.
     *
     * @param array<int, int> $sides
     */
    public function bracelessLoopMismatch(object $shape, object $model, array $sides, bool $flag): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side)
                while ($flag) {
                    return 'Round';
                }
        elseif ($model->kind === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';
        elseif ($shape->type === 'triangle')
            return 'Triangle';

        return 'Unknown';
    }
}
