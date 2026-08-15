<?php

/**
 * Classes that reach or pass the default threshold of 13 distinct
 * dependencies, each reported once on its own declaration line.
 */

declare(strict_types=1);

namespace Fail;

/**
 * Thirteen distinct dependencies — exactly the threshold, and already a
 * violation, because PHPMD's comparison is inclusive.
 */
class AtTheThreshold
{
    private \Fail\Dep\D01 $held;

    public function __construct(private \Fail\Dep\D02 $promoted)
    {
    }

    public function m(\Fail\Dep\D03 $a, \Fail\Dep\D04|\Fail\Dep\D05 $b): ?\Fail\Dep\D06
    {
        $made = new \Fail\Dep\D07();
        \Fail\Dep\D08::make();
        $value = \Fail\Dep\D09::VALUE;
        $name = \Fail\Dep\D10::class;

        if ($made instanceof \Fail\Dep\D11) {
            return null;
        }

        try {
            $this->m($a, $b);
        } catch (\Fail\Dep\D12 $e) {
        }

        $closure = function (\Fail\Dep\D13 $c) {
            return $c;
        };

        return null;
    }
}

/**
 * Well past the threshold, and through parameter types alone.
 */
class FarPastTheThreshold
{
    public function m(
        \Fail\Dep\P01 $a,
        \Fail\Dep\P02 $b,
        \Fail\Dep\P03 $c,
        \Fail\Dep\P04 $d,
        \Fail\Dep\P05 $e,
        \Fail\Dep\P06 $f,
        \Fail\Dep\P07 $g,
        \Fail\Dep\P08 $h,
        \Fail\Dep\P09 $i,
        \Fail\Dep\P10 $j,
        \Fail\Dep\P11 $k,
        \Fail\Dep\P12 $l,
        \Fail\Dep\P13 $m,
        \Fail\Dep\P14 $n,
        \Fail\Dep\P15 $o,
        \Fail\Dep\P16 $p,
        \Fail\Dep\P17 $q,
        \Fail\Dep\P18 $r
    ): void {
    }
}
