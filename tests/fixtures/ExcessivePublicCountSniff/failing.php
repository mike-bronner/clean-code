<?php

/**
 * Code CleanCode.Metrics.ExcessivePublicCount must flag: a class exactly at the
 * inclusive threshold, a class reaching it through a mix of methods and
 * properties, and a trait at the threshold.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Fixtures\ExcessivePublicCount;

/** Exactly 45 public methods - the threshold is inclusive, so this is a violation. */
class AtTheThreshold
{
    public function m1(): void
    {
    }

    public function m2(): void
    {
    }

    public function m3(): void
    {
    }

    public function m4(): void
    {
    }

    public function m5(): void
    {
    }

    public function m6(): void
    {
    }

    public function m7(): void
    {
    }

    public function m8(): void
    {
    }

    public function m9(): void
    {
    }

    public function m10(): void
    {
    }

    public function m11(): void
    {
    }

    public function m12(): void
    {
    }

    public function m13(): void
    {
    }

    public function m14(): void
    {
    }

    public function m15(): void
    {
    }

    public function m16(): void
    {
    }

    public function m17(): void
    {
    }

    public function m18(): void
    {
    }

    public function m19(): void
    {
    }

    public function m20(): void
    {
    }

    public function m21(): void
    {
    }

    public function m22(): void
    {
    }

    public function m23(): void
    {
    }

    public function m24(): void
    {
    }

    public function m25(): void
    {
    }

    public function m26(): void
    {
    }

    public function m27(): void
    {
    }

    public function m28(): void
    {
    }

    public function m29(): void
    {
    }

    public function m30(): void
    {
    }

    public function m31(): void
    {
    }

    public function m32(): void
    {
    }

    public function m33(): void
    {
    }

    public function m34(): void
    {
    }

    public function m35(): void
    {
    }

    public function m36(): void
    {
    }

    public function m37(): void
    {
    }

    public function m38(): void
    {
    }

    public function m39(): void
    {
    }

    public function m40(): void
    {
    }

    public function m41(): void
    {
    }

    public function m42(): void
    {
    }

    public function m43(): void
    {
    }

    public function m44(): void
    {
    }

    public function m45(): void
    {
    }
}

/** 20 public methods plus 25 public properties - methods and attributes count alike. */
class MixedPublicMembers
{
    public int $p1 = 1;
    public int $p2 = 2;
    public int $p3 = 3;
    public int $p4 = 4;
    public int $p5 = 5;
    public int $p6 = 6;
    public int $p7 = 7;
    public int $p8 = 8;
    public int $p9 = 9;
    public int $p10 = 10;
    public int $p11 = 11;
    public int $p12 = 12;
    public int $p13 = 13;
    public int $p14 = 14;
    public int $p15 = 15;
    public int $p16 = 16;
    public int $p17 = 17;
    public int $p18 = 18;
    public int $p19 = 19;
    public int $p20 = 20;
    public int $p21 = 21;
    public int $p22 = 22;
    public int $p23 = 23;
    public int $p24 = 24;
    public int $p25 = 25;

    public function m1(): void
    {
    }

    public function m2(): void
    {
    }

    public function m3(): void
    {
    }

    public function m4(): void
    {
    }

    public function m5(): void
    {
    }

    public function m6(): void
    {
    }

    public function m7(): void
    {
    }

    public function m8(): void
    {
    }

    public function m9(): void
    {
    }

    public function m10(): void
    {
    }

    public function m11(): void
    {
    }

    public function m12(): void
    {
    }

    public function m13(): void
    {
    }

    public function m14(): void
    {
    }

    public function m15(): void
    {
    }

    public function m16(): void
    {
    }

    public function m17(): void
    {
    }

    public function m18(): void
    {
    }

    public function m19(): void
    {
    }

    public function m20(): void
    {
    }
}

/** 45 public methods on a trait, which PHPMD checks exactly as it checks a class. */
trait WideTrait
{
    public function m1(): void
    {
    }

    public function m2(): void
    {
    }

    public function m3(): void
    {
    }

    public function m4(): void
    {
    }

    public function m5(): void
    {
    }

    public function m6(): void
    {
    }

    public function m7(): void
    {
    }

    public function m8(): void
    {
    }

    public function m9(): void
    {
    }

    public function m10(): void
    {
    }

    public function m11(): void
    {
    }

    public function m12(): void
    {
    }

    public function m13(): void
    {
    }

    public function m14(): void
    {
    }

    public function m15(): void
    {
    }

    public function m16(): void
    {
    }

    public function m17(): void
    {
    }

    public function m18(): void
    {
    }

    public function m19(): void
    {
    }

    public function m20(): void
    {
    }

    public function m21(): void
    {
    }

    public function m22(): void
    {
    }

    public function m23(): void
    {
    }

    public function m24(): void
    {
    }

    public function m25(): void
    {
    }

    public function m26(): void
    {
    }

    public function m27(): void
    {
    }

    public function m28(): void
    {
    }

    public function m29(): void
    {
    }

    public function m30(): void
    {
    }

    public function m31(): void
    {
    }

    public function m32(): void
    {
    }

    public function m33(): void
    {
    }

    public function m34(): void
    {
    }

    public function m35(): void
    {
    }

    public function m36(): void
    {
    }

    public function m37(): void
    {
    }

    public function m38(): void
    {
    }

    public function m39(): void
    {
    }

    public function m40(): void
    {
    }

    public function m41(): void
    {
    }

    public function m42(): void
    {
    }

    public function m43(): void
    {
    }

    public function m44(): void
    {
    }

    public function m45(): void
    {
    }
}
