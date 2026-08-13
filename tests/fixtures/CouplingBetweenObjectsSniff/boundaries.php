<?php

/**
 * The inclusive boundary, in one file: twelve dependencies is silent, thirteen
 * is a violation, fourteen is a violation.
 */

declare(strict_types=1);

namespace Bound;

class TwelveDependencies
{
    public function m(
        \Bound\Dep\A01 $a01,
        \Bound\Dep\A02 $a02,
        \Bound\Dep\A03 $a03,
        \Bound\Dep\A04 $a04,
        \Bound\Dep\A05 $a05,
        \Bound\Dep\A06 $a06,
        \Bound\Dep\A07 $a07,
        \Bound\Dep\A08 $a08,
        \Bound\Dep\A09 $a09,
        \Bound\Dep\A10 $a10,
        \Bound\Dep\A11 $a11,
        \Bound\Dep\A12 $a12
    ): void {
    }
}

class ThirteenDependencies
{
    public function m(
        \Bound\Dep\B01 $b01,
        \Bound\Dep\B02 $b02,
        \Bound\Dep\B03 $b03,
        \Bound\Dep\B04 $b04,
        \Bound\Dep\B05 $b05,
        \Bound\Dep\B06 $b06,
        \Bound\Dep\B07 $b07,
        \Bound\Dep\B08 $b08,
        \Bound\Dep\B09 $b09,
        \Bound\Dep\B10 $b10,
        \Bound\Dep\B11 $b11,
        \Bound\Dep\B12 $b12,
        \Bound\Dep\B13 $b13
    ): void {
    }
}

class FourteenDependencies
{
    public function m(
        \Bound\Dep\C01 $c01,
        \Bound\Dep\C02 $c02,
        \Bound\Dep\C03 $c03,
        \Bound\Dep\C04 $c04,
        \Bound\Dep\C05 $c05,
        \Bound\Dep\C06 $c06,
        \Bound\Dep\C07 $c07,
        \Bound\Dep\C08 $c08,
        \Bound\Dep\C09 $c09,
        \Bound\Dep\C10 $c10,
        \Bound\Dep\C11 $c11,
        \Bound\Dep\C12 $c12,
        \Bound\Dep\C13 $c13,
        \Bound\Dep\C14 $c14
    ): void {
    }
}
