<?php

/**
 * A declaration cut short mid-edit: bodiless, like an abstract method, but
 * missing the semicolon that would end it.
 *
 * It has neither a closing brace to measure to nor a terminator of its own, so
 * the only semicolon ahead of it belongs to a statement inside the *next*
 * declaration. Measuring to that one would score this fragment thirteen lines
 * instead of one, which is how a search for "the next semicolon" turns a typo
 * into a violation. The fragment is scored one line and never reported.
 *
 * PHPMD is not a reference here: PDepend cannot parse the file at all, so there
 * is no live run to compare against and no parity to claim. The behaviour below
 * is this ruleset's own fail-closed choice for input neither tool can read.
 */

abstract class TruncatedDeclaration
{
    abstract public function missingItsSemicolon(): void

    public function theNextDeclaration(): void
    {
        $first = 1;
        $second = 2;
        $third = 3;
        $fourth = 4;
        $fifth = 5;
    }
}
