<?php

/**
 * The four shapes where this sniff reports and phpmd does not.
 *
 * Verified against phpmd 2.15 with rulesets/naming.xml/ShortVariable, from a
 * cold pdepend cache: this file produces no output and exit code 0, while
 * phpcs reports every name below. Each name really is shorter than the
 * minimum, so none of the reports is a false positive — only a report
 * phpmd's tree, or its own allowed-context list, does not reach.
 *
 * Reporting them is the safe direction. This ruleset exists so phpmd does not
 * have to run; reporting more than phpmd never leaves a real violation
 * unreported, while falling silent where phpmd speaks would.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

// 1. Procedural code. pdepend hands phpmd class, trait, function and method
//    nodes only, so a file-level variable is never visited at any threshold.
$tl = 1;

echo $tl;

class Diverging
{
    public function memberAccess(Diverging $other): int
    {
        // 2. A name whose first occurrence sits inside a `->` or `::` chain.
        //    PHPMD's isNameAllowedInContext() exempts the whole
        //    MemberPrimaryPrefix subtree, which swallows the arguments of the
        //    call as well as the object it is called on.
        $other->memberAccess($ar);
        Diverging::stat($br);

        return $other->memberAccess($ar) + $cr->value;
    }

    public function caughtBlock(): int
    {
        try {
            return 1;
        } catch (\Throwable $ex) {
            // 3. A name declared inside a catch block's body. PHPMD exempts
            //    everything under the CatchStatement node, not just the
            //    variable the catch binds. `$ex` itself is exempt in both
            //    tools, so its silence here is parity, not divergence.
            $cb = (int) $ex->getCode();

            return $cb;
        }
    }

    public static function stat(int $number): int
    {
        return $number;
    }
}

// 4. An anonymous class. pdepend builds no class node for `new class`, so
//    neither its fields nor its methods' parameters and locals are visited.
$anonymous = new class {
    public $cf = 1;

    public function anon($mp): int
    {
        $al = 2;

        return $mp + $al + $this->cf;
    }
};

echo $anonymous->anon(1);
