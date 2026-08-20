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
 *   - deepPropertySwitch      a property read two hops deep
 *   - deepPropertyIf          the same, in the if form
 *   - positionalIndexSwitch   a positional index rather than a named field
 *   - staticPropertySwitch    a static read, which belongs to the class
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
}
