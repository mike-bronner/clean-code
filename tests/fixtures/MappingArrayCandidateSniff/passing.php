<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Conditionals.MappingArrayCandidate.
 *
 * The compliant form is the mapping array itself, and it opens the file. Every
 * method after it is a *near miss*: a chain that reaches the branch count and
 * looks mapping-shaped, but breaks exactly one of the sniff's rules. Each one
 * pins a different exclusion, so relaxing any single rule reddens this file:
 *
 *   - twoBranches         below the configured minimum
 *   - greaterThan         a non-equality operator
 *   - compound            a compound boolean condition
 *   - calledCondition     a call in the condition
 *   - parenthesised       an extra parenthesis pair — not three tokens
 *   - notIdentical        `!==`, which is not one of the two named comparisons
 *   - subtractedCondition arithmetic on a condition operand, not a literal
 *   - differentSubjects   a different variable in one branch
 *   - propertySubject     a property read rather than a plain variable
 *   - nullLiteral         `null`, which is not a scalar
 *   - multiStatement      two statements in a branch body
 *   - sideEffect          a call in a branch expression
 *   - subtractedValue     arithmetic between two operands in a branch expression
 *   - emptyBranch         a branch that produces no value at all
 *   - mixedBodies         `return` in one branch, assignment in another
 *   - differentTargets    assignments to two different targets
 *   - compoundAssignment  `.=`, which accumulates rather than selects
 *   - nestedAssignment    an assignment buried inside a returned expression
 *   - bareReturn          `return;`, which produces no value to map
 *   - interpolatedLiteral a double-quoted string with a variable in it
 *   - switchStatement     a `switch` of exactly the qualifying shape
 *   - matchExpression     a `match` of exactly the qualifying shape
 */

final class NearMisses
{
    private const LABELS = [
        'a' => 'Alpha',
        'b' => 'Bravo',
        'c' => 'Charlie',
    ];

    public string $status = 'new';

    public function compliant(string $code): string
    {
        return self::LABELS[$code] ?? 'Unknown';
    }

    public function twoBranches(string $code): string
    {
        if ($code === 'a') {
            return 'Alpha';
        } else {
            return 'Unknown';
        }
    }

    public function greaterThan(int $level): string
    {
        if ($level === 1) {
            return 'low';
        } elseif ($level > 2) {
            return 'high';
        } else {
            return 'unknown';
        }
    }

    public function compound(string $code): string
    {
        if ($code === 'a' && $code !== '') {
            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function calledCondition(string $code): string
    {
        if (strtolower($code) === 'a') {
            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function parenthesised(string $code): string
    {
        if (($code === 'a')) {
            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function notIdentical(string $code): string
    {
        if ($code !== 'a') {
            return 'Alpha';
        } elseif ($code !== 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    /**
     * The sign token that makes `-1` a literal is also PHP's subtraction
     * operator. Here it joins two operands instead, so the right-hand side is
     * an expression rather than a literal and the chain is not a lookup.
     */
    public function subtractedCondition(int $code, int $offset): string
    {
        if ($code === $offset - 1) {
            return 'Alpha';
        } elseif ($code === 2) {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function differentSubjects(string $code, string $other): string
    {
        if ($code === 'a') {
            return 'Alpha';
        } elseif ($other === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function propertySubject(): string
    {
        if ($this->status === 'new') {
            return 'New';
        } elseif ($this->status === 'open') {
            return 'Open';
        } else {
            return 'Closed';
        }
    }

    public function nullLiteral(?string $code): string
    {
        if ($code === null) {
            return 'None';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function multiStatement(string $code): string
    {
        if ($code === 'a') {
            $this->status = 'seen';

            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function sideEffect(string $code): string
    {
        if ($code === 'a') {
            return strtoupper('alpha');
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Subtraction between two variables is work, and a mapping array evaluates
     * every value the moment it is built — so hoisting this branch into a map
     * would run it on every call rather than on the one that selects it.
     */
    public function subtractedValue(int $code, int $first, int $second): int
    {
        if ($code === 1) {
            return $first - $second;
        } elseif ($code === 2) {
            return 2;
        } else {
            return 3;
        }
    }

    public function emptyBranch(string $code): string
    {
        if ($code === 'a') {
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }

        return 'Alpha';
    }

    public function mixedBodies(string $code): string
    {
        $label = 'Unknown';

        if ($code === 'a') {
            return 'Alpha';
        } elseif ($code === 'b') {
            $label = 'Bravo';
        } else {
            $label = 'Charlie';
        }

        return $label;
    }

    public function differentTargets(string $code): string
    {
        $first = 'Unknown';
        $second = 'Unknown';

        if ($code === 'a') {
            $first = 'Alpha';
        } elseif ($code === 'b') {
            $second = 'Bravo';
        } else {
            $first = 'Charlie';
        }

        return $first . $second;
    }

    public function compoundAssignment(string $code): string
    {
        $label = '';

        if ($code === 'a') {
            $label .= 'Alpha';
        } elseif ($code === 'b') {
            $label .= 'Bravo';
        } else {
            $label .= 'Unknown';
        }

        return $label;
    }

    public function nestedAssignment(string $code): string
    {
        if ($code === 'a') {
            return $this->status = 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function bareReturn(string $code): void
    {
        if ($code === 'a') {
            return;
        } elseif ($code === 'b') {
            return;
        } else {
            return;
        }
    }

    public function interpolatedLiteral(string $code, string $suffix): string
    {
        if ($code === "a$suffix") {
            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    public function switchStatement(string $code): string
    {
        switch ($code) {
            case 'a':
                return 'Alpha';
            case 'b':
                return 'Bravo';
            default:
                return 'Unknown';
        }
    }

    public function matchExpression(string $code): string
    {
        return match ($code) {
            'a' => 'Alpha',
            'b' => 'Bravo',
            default => 'Unknown',
        };
    }
}
