<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Conditionals.CombinableConditions.
 *
 * The compliant form — the conditions already combined with `||` — opens the
 * file. Every method after it is a *near miss*: two branches that look
 * combinable and break exactly one of the sniff's rules, so relaxing any single
 * rule reddens this file rather than going unnoticed:
 *
 *   - repeatedWithoutExit    identical separate `if`s whose body does not exit
 *   - differentBodies        separate exiting `if`s with different bodies
 *   - statementBetween       identical exiting `if`s with a statement between
 *   - assignmentBetween      the same, where the statement is an assignment
 *   - firstHasElse           the first `if` carries an `else`
 *   - secondHasElse          the second `if` carries an `else`
 *   - firstHasElseif         the first `if` carries an `elseif`
 *   - nonAdjacentBranches    a chain whose identical branches are not adjacent
 *   - elseRepeatsBranch      a chain whose `else` repeats the last branch
 *   - differingLiteral       branches whose only difference is a literal
 *   - differingVariable      branches whose only difference is a variable name
 *   - emptyBodies            two empty branches, which combine to nothing
 *   - nestedIfBodies         brace-less bodies that are themselves `if`s
 *   - nestedLoopBodies       brace-less bodies that are themselves loops
 *   - conditionalExit        identical bodies whose exit is nested, not final
 *   - separateScopes         identical exiting `if`s in two different methods
 */

final class NearMisses
{
    public function combined(?string $name, ?string $email): void
    {
        if ($name === null || $email === null) {
            return;
        }

        $this->log($name);
    }

    public function repeatedWithoutExit(int $a, int $b): void
    {
        if ($a === 1) {
            $this->log('hit');
        }

        if ($b === 2) {
            $this->log('hit');
        }
    }

    public function differentBodies(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        if ($email === null) {
            throw new InvalidArgumentException('missing');
        }
    }

    public function statementBetween(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        $this->log('checked');

        if ($email === null) {
            return;
        }
    }

    public function assignmentBetween(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        $checked = true;

        if ($email === null) {
            return;
        }

        $this->log((string) $checked);
    }

    public function firstHasElse(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        } else {
            $this->log($name);
        }

        if ($email === null) {
            return;
        }
    }

    public function secondHasElse(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        if ($email === null) {
            return;
        } else {
            $this->log($email);
        }
    }

    public function firstHasElseif(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        } elseif ($name === '') {
            $this->log('empty');
        }

        if ($email === null) {
            return;
        }
    }

    public function nonAdjacentBranches(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) {
            return 'other';
        } elseif ($code === 3) {
            return 'same';
        }

        return 'none';
    }

    public function elseRepeatsBranch(int $code): string
    {
        if ($code === 1) {
            return 'one';
        } elseif ($code === 2) {
            return 'other';
        } else {
            return 'other';
        }
    }

    public function differingLiteral(int $code): int
    {
        if ($code === 1) {
            return 1;
        } elseif ($code === 2) {
            return 2;
        }

        return 0;
    }

    public function differingVariable(int $code, string $first, string $second): string
    {
        if ($code === 1) {
            return $first;
        } elseif ($code === 2) {
            return $second;
        }

        return '';
    }

    public function emptyBodies(int $a, int $b): void
    {
        if ($a === 1) {
        } elseif ($b === 2) {
        }
    }

    public function nestedIfBodies(int $a, int $b, bool $ready): void
    {
        if ($a === 1) if ($ready) return;
        if ($b === 2) if ($ready) return;
    }

    public function nestedLoopBodies(int $a, int $b, array $rows): void
    {
        if ($a === 1) foreach ($rows as $row) $this->log((string) $row);
        if ($b === 2) foreach ($rows as $row) $this->log((string) $row);
    }

    public function conditionalExit(?string $name, ?string $email, bool $strict): void
    {
        if ($name === null) {
            if ($strict) {
                return;
            }
        }

        if ($email === null) {
            if ($strict) {
                return;
            }
        }
    }

    public function separateScopes(?string $name): void
    {
        if ($name === null) {
            return;
        }

        $this->log($name);
    }

    public function separateScopesTwin(?string $email): void
    {
        if ($email === null) {
            return;
        }

        $this->log($email);
    }

    private function log(string $message): void
    {
    }
}
