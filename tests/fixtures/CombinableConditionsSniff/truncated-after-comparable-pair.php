<?php

declare(strict_types=1);

/**
 * A chain whose identical leading pair is complete, and whose last branch runs
 * off the end of the file, for CleanCode.Conditionals.CombinableConditions.
 *
 * The sibling truncated-*.php fixtures cut the file where *nothing* is
 * measurable, so silence there proves only that the walk terminates. This one
 * cuts it after two branches that were each read in full: the pair is as
 * combinable as it would be in a whole file, and the branch that never arrived
 * says nothing about it. A walk that treated an unreadable branch as fatal to
 * its chain would report nothing here.
 *
 * The truncation is deliberate and this file does not parse. Line numbers are
 * asserted exactly in tests/Standards/CombinableConditionsTest.php.
 */

final class TruncatedAfterComparablePair
{
    public function chainPairThenTruncation(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) {
            return 'same';
        } elseif ($code === 3
