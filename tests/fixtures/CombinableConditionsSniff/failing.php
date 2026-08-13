<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Conditionals.CombinableConditions.
 *
 * Two shapes are flagged, and both appear here:
 *
 *   - adjacent branches of one if/elseif chain with identical bodies, whatever
 *     those bodies do;
 *   - adjacent separate plain `if` statements with identical bodies that
 *     unconditionally exit the enclosing scope.
 *
 * Every participating branch is reported at its own keyword, so a pair is two
 * warnings and a run of three is three.
 *
 * Line numbers are asserted exactly in tests/Standards/CombinableConditionsTest.php.
 */

final class Combinable
{
    // 27 + 29 — chain branches, and the body does not exit: only one branch of
    // a chain ever runs, so no exit requirement applies to this shape.
    public function chainWithoutExit(int $code): void
    {
        if ($code === 1) {
            $this->log('low');
        } elseif ($code === 2) {
            $this->log('low');
        } elseif ($code === 3) {
            $this->log('high');
        }
    }

    // 42 + 44 — only the adjacent identical pair is reported; the first branch
    // differs and the trailing `else` carries no condition to combine.
    public function middlePair(int $code): string
    {
        if ($code === 1) {
            return 'one';
        } elseif ($code === 2) {
            return 'other';
        } elseif ($code === 3) {
            return 'other';
        } else {
            return 'other';
        }
    }

    // 54 + 56 + 58 — three adjacent identical branches, all three reported.
    public function threeBranches(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) {
            return 'same';
        } elseif ($code === 3) {
            return 'same';
        }

        return 'other';
    }

    // 68 + 72 — separate guard clauses that return.
    public function returningGuards(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        if ($email === null) {
            return;
        }

        $this->log($name);
    }

    // 82 + 86 — the same, throwing.
    public function throwingGuards(?string $name, ?string $email): void
    {
        if ($name === null) {
            throw new InvalidArgumentException('missing');
        }

        if ($email === null) {
            throw new InvalidArgumentException('missing');
        }
    }

    // 94 + 98 — the same, exiting the script.
    public function exitingGuards(?string $name, ?string $email): void
    {
        if ($name === null) {
            exit(1);
        }

        if ($email === null) {
            exit(1);
        }
    }

    // 107 + 111 — the same, continuing a loop.
    public function continuingGuards(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row === null) {
                continue;
            }

            if ($row === '') {
                continue;
            }

            $this->log((string) $row);
        }
    }

    // 123 + 127 — the same, breaking a loop.
    public function breakingGuards(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row === null) {
                break;
            }

            if ($row === '') {
                break;
            }
        }
    }

    // 136 + 140 + 144 — a run of three, all three reported.
    public function threeGuards(?string $a, ?string $b, ?string $c): void
    {
        if ($a === null) {
            return;
        }

        if ($b === null) {
            return;
        }

        if ($c === null) {
            return;
        }

        $this->log($a);
    }

    // 155 + 160 — a comment between two `if`s is not a statement, so the two
    // are still adjacent.
    public function commentBetweenGuards(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        // The email is optional in one caller and required here.
        if ($email === null) {
            return;
        }

        $this->log($name);
    }

    // 172 + 174 — the identical pair is still reported although the branch
    // after it is one the sniff cannot read: a brace-less nested `if` is
    // skipped, not contagious.
    public function chainPairThenUnreadableBranch(int $code, bool $ready): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) {
            return 'same';
        } elseif ($code === 3) if ($ready) return 'other';

        return 'none';
    }

    // 186 + 188 — the same, where the unreadable branch is a brace-less loop
    // rather than a nested `if`. What ends the chain is that the clause cannot
    // be measured, not which construct made it unmeasurable.
    public function chainPairThenUnreadableLoop(int $code, array $rows): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) {
            return 'same';
        } elseif ($code === 3) foreach ($rows as $row) $this->log((string) $row);

        return 'none';
    }

    private function log(string $message): void
    {
    }
}
