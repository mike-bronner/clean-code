<?php

/**
 * Violating input for CleanCode.Naming.ShortVariable.
 *
 * Every shape PHPMD's rule reaches: a property (its ClassAware half), a trait
 * field (TraitAware), a parameter of a global function (FunctionAware), of an
 * interface, enum and class method (MethodAware), a promoted constructor
 * parameter, a local, a static and a global declaration, a destructured
 * variable, a by-reference foreach value, a closure and arrow-function
 * parameter, and a name that only ever appears interpolated into a string.
 *
 * Every line below was confirmed to be reported by phpmd 2.15 running
 * rulesets/naming.xml/ShortVariable at its defaults, from a cold pdepend
 * cache — same lines, same names, same count.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

function shortsInAFunction($fp): int
{
    $fl = $fp;

    return $fl;
}

trait Shorts
{
    private $tf = 1;
}

interface Contract
{
    public function contracted($ip): int;
}

enum Suit: string
{
    case Hearts = 'H';

    public function enumerated($ep): string
    {
        return $this->value . $ep;
    }
}

class Offender implements Contract
{
    private $qq = 15;

    public static $sq = 1;

    public int $tq = 0;

    public function __construct(private int $pp)
    {
    }

    public function contracted($ip): int
    {
        return $ip + $this->pp;
    }

    public static function main(array $as, $bb = 1): int
    {
        $rr = 20 + $as[0] + $bb;
        // A second occurrence of a name already reported. PHPMD reports each
        // name once per scope, at its first occurrence, and so does this.
        $rr += 1;

        return $rr;
    }

    public function destructures(array $pairs): int
    {
        [$d1, $d2] = $pairs;

        // The key is exempt as a loop variable; the variables destructured
        // out of the value are not, in either tool.
        foreach ($pairs as $ky => [$e1, $e2]) {
            $d1 += $ky + $e1 + $e2;
        }

        // A by-reference value is not exempt either: PHPMD matches the loop
        // variable by image against the foreach's own children, and the
        // reference expression is not one of them.
        foreach ($pairs as &$rv) {
            $rv = 1;
        }

        return $d1 + $d2;
    }

    public function interpolates(): string
    {
        // Names that only ever appear interpolated into a string. pdepend
        // parses both into ordinary variable nodes, so phpmd reports them,
        // and so does this.
        return "value $zz" . <<<TXT
            heredoc $hd
            TXT;
    }

    public function statics(): int
    {
        static $sv = 3;
        global $gv;

        return $sv + $gv;
    }

    public function closures(): int
    {
        // A closure and an arrow function fold into the scope around them in
        // both tools, so their parameters and locals are reported as part of
        // this method.
        $call = function ($cp): int {
            $lv = 1;

            return $cp + $lv;
        };
        $arrow = fn (int $ap): int => $ap;

        return $call(1) + $arrow(2);
    }
}
