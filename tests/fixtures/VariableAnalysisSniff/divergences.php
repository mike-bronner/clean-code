<?php

declare(strict_types=1);

namespace App;

/**
 * Every case where the configured sniff and PHPMD's UndefinedVariable rule do
 * not behave identically. Each was checked by running both tools over this
 * file: PHPMD 2.15.0 with only UndefinedVariable enabled, and phpcs with the
 * master CleanCode/ruleset.xml.
 *
 * The two tools ask different questions. PHPMD asks whether the enclosing
 * method assigns the name anywhere at all; the sniff asks whether an
 * assignment has already been reached, in this scope, at the point of the
 * read. Neither tool asks whether the assignment is reachable on every path.
 *
 * This file is not compliant code. It is the record of the gap, kept as a
 * fixture so the gap is pinned by a test rather than only described in prose.
 */
class Divergences
{
    /**
     * Sniff flags this line; PHPMD does not. The read sits one line above its
     * own assignment, in the same method scope, with no branching. PHPMD sees
     * the scope assigns $assignedLater and stays silent; the sniff reads
     * statements in order and reports the read as undefined.
     *
     * The sniff is stricter here, and correct: the read really does evaluate
     * to null at runtime.
     */
    public function readsAboveItsOwnAssignment(): int
    {
        $total = $assignedLater + 1;
        $assignedLater = 2;

        return $total;
    }

    /**
     * Sniff flags this line; PHPMD does not. A closure body is its own scope
     * to the sniff, so $insideClosure never becomes defined for the enclosing
     * method. PHPMD folds the closure's assignments into the method that
     * declares it and stays silent.
     *
     * The sniff is stricter here too, and correct: a closure local does not
     * leak into its enclosing scope.
     */
    public function readsAClosureLocalOutsideTheClosure(): string
    {
        $writer = static function (): void {
            $insideClosure = 'written';
        };
        $writer();

        return $insideClosure;
    }

    /**
     * Neither tool flags this line — a shared blind spot, not a divergence
     * between them. $onlyOnTheTrueBranch is assigned on one branch only and
     * then read unconditionally, so the read evaluates to null whenever $flag
     * is false. PHPMD stays silent because the method does assign the name
     * somewhere; the sniff stays silent because the assignment is reached
     * before the read in statement order.
     *
     * Path reachability is beyond both tools. This shape stays code review.
     */
    public function readsAConditionallyAssignedName(bool $flag): string
    {
        if ($flag) {
            $onlyOnTheTrueBranch = 'set';
        }

        return $onlyOnTheTrueBranch;
    }
}
