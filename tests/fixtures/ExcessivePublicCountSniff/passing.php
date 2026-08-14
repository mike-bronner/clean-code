<?php

/**
 * Compliant code for CleanCode.Metrics.ExcessivePublicCount, plus the near-miss
 * shapes the sniff must stay silent on: one member below the threshold, a wide
 * but non-public surface, constants, interfaces and enums (which PHPMD does not
 * check either), an anonymous class nested in a slim host, and a method stuffed
 * with local variables.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Fixtures\ExcessivePublicCount;

/** 44 public methods - one below the inclusive threshold of 45. */
class JustUnderTheThreshold
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
}

/** 44 public methods on a trait - traits are checked, and this one is under. */
trait JustUnderTheThresholdTrait
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
}

/**
 * 50 non-public methods and 50 non-public properties around 10 public methods.
 * Only the public surface is measured, so a wide type stays silent.
 */
class MostlyHidden
{
    private int $hidden1 = 1;
    private int $hidden2 = 2;
    private int $hidden3 = 3;
    private int $hidden4 = 4;
    private int $hidden5 = 5;
    private int $hidden6 = 6;
    private int $hidden7 = 7;
    private int $hidden8 = 8;
    private int $hidden9 = 9;
    private int $hidden10 = 10;
    private int $hidden11 = 11;
    private int $hidden12 = 12;
    private int $hidden13 = 13;
    private int $hidden14 = 14;
    private int $hidden15 = 15;
    private int $hidden16 = 16;
    private int $hidden17 = 17;
    private int $hidden18 = 18;
    private int $hidden19 = 19;
    private int $hidden20 = 20;
    private int $hidden21 = 21;
    private int $hidden22 = 22;
    private int $hidden23 = 23;
    private int $hidden24 = 24;
    private int $hidden25 = 25;
    private int $hidden26 = 26;
    private int $hidden27 = 27;
    private int $hidden28 = 28;
    private int $hidden29 = 29;
    private int $hidden30 = 30;
    private int $hidden31 = 31;
    private int $hidden32 = 32;
    private int $hidden33 = 33;
    private int $hidden34 = 34;
    private int $hidden35 = 35;
    private int $hidden36 = 36;
    private int $hidden37 = 37;
    private int $hidden38 = 38;
    private int $hidden39 = 39;
    private int $hidden40 = 40;
    private int $hidden41 = 41;
    private int $hidden42 = 42;
    private int $hidden43 = 43;
    private int $hidden44 = 44;
    private int $hidden45 = 45;
    private int $hidden46 = 46;
    private int $hidden47 = 47;
    private int $hidden48 = 48;
    private int $hidden49 = 49;
    private int $hidden50 = 50;

    private function h1(): void
    {
    }

    protected function h2(): void
    {
    }

    private function h3(): void
    {
    }

    protected function h4(): void
    {
    }

    private function h5(): void
    {
    }

    protected function h6(): void
    {
    }

    private function h7(): void
    {
    }

    protected function h8(): void
    {
    }

    private function h9(): void
    {
    }

    protected function h10(): void
    {
    }

    private function h11(): void
    {
    }

    protected function h12(): void
    {
    }

    private function h13(): void
    {
    }

    protected function h14(): void
    {
    }

    private function h15(): void
    {
    }

    protected function h16(): void
    {
    }

    private function h17(): void
    {
    }

    protected function h18(): void
    {
    }

    private function h19(): void
    {
    }

    protected function h20(): void
    {
    }

    private function h21(): void
    {
    }

    protected function h22(): void
    {
    }

    private function h23(): void
    {
    }

    protected function h24(): void
    {
    }

    private function h25(): void
    {
    }

    protected function h26(): void
    {
    }

    private function h27(): void
    {
    }

    protected function h28(): void
    {
    }

    private function h29(): void
    {
    }

    protected function h30(): void
    {
    }

    private function h31(): void
    {
    }

    protected function h32(): void
    {
    }

    private function h33(): void
    {
    }

    protected function h34(): void
    {
    }

    private function h35(): void
    {
    }

    protected function h36(): void
    {
    }

    private function h37(): void
    {
    }

    protected function h38(): void
    {
    }

    private function h39(): void
    {
    }

    protected function h40(): void
    {
    }

    private function h41(): void
    {
    }

    protected function h42(): void
    {
    }

    private function h43(): void
    {
    }

    protected function h44(): void
    {
    }

    private function h45(): void
    {
    }

    protected function h46(): void
    {
    }

    private function h47(): void
    {
    }

    protected function h48(): void
    {
    }

    private function h49(): void
    {
    }

    protected function h50(): void
    {
    }

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
}

/**
 * 50 public constants. PHPMD counts methods and *attributes*; a constant is
 * neither, so the count here is 1 - the single public method.
 */
class ConstantsAreNotAttributes
{
    public const C1 = 1;
    public const C2 = 2;
    public const C3 = 3;
    public const C4 = 4;
    public const C5 = 5;
    public const C6 = 6;
    public const C7 = 7;
    public const C8 = 8;
    public const C9 = 9;
    public const C10 = 10;
    public const C11 = 11;
    public const C12 = 12;
    public const C13 = 13;
    public const C14 = 14;
    public const C15 = 15;
    public const C16 = 16;
    public const C17 = 17;
    public const C18 = 18;
    public const C19 = 19;
    public const C20 = 20;
    public const C21 = 21;
    public const C22 = 22;
    public const C23 = 23;
    public const C24 = 24;
    public const C25 = 25;
    public const C26 = 26;
    public const C27 = 27;
    public const C28 = 28;
    public const C29 = 29;
    public const C30 = 30;
    public const C31 = 31;
    public const C32 = 32;
    public const C33 = 33;
    public const C34 = 34;
    public const C35 = 35;
    public const C36 = 36;
    public const C37 = 37;
    public const C38 = 38;
    public const C39 = 39;
    public const C40 = 40;
    public const C41 = 41;
    public const C42 = 42;
    public const C43 = 43;
    public const C44 = 44;
    public const C45 = 45;
    public const C46 = 46;
    public const C47 = 47;
    public const C48 = 48;
    public const C49 = 49;
    public const C50 = 50;

    public function m1(): void
    {
    }
}

/**
 * 50 public methods on an interface. PHPMD's rule is ClassAware and TraitAware
 * only, and PDepend refuses to compute class-level metrics for interfaces at
 * all, so neither tool reports this.
 */
interface WideInterface
{
    public function m1(): void;

    public function m2(): void;

    public function m3(): void;

    public function m4(): void;

    public function m5(): void;

    public function m6(): void;

    public function m7(): void;

    public function m8(): void;

    public function m9(): void;

    public function m10(): void;

    public function m11(): void;

    public function m12(): void;

    public function m13(): void;

    public function m14(): void;

    public function m15(): void;

    public function m16(): void;

    public function m17(): void;

    public function m18(): void;

    public function m19(): void;

    public function m20(): void;

    public function m21(): void;

    public function m22(): void;

    public function m23(): void;

    public function m24(): void;

    public function m25(): void;

    public function m26(): void;

    public function m27(): void;

    public function m28(): void;

    public function m29(): void;

    public function m30(): void;

    public function m31(): void;

    public function m32(): void;

    public function m33(): void;

    public function m34(): void;

    public function m35(): void;

    public function m36(): void;

    public function m37(): void;

    public function m38(): void;

    public function m39(): void;

    public function m40(): void;

    public function m41(): void;

    public function m42(): void;

    public function m43(): void;

    public function m44(): void;

    public function m45(): void;

    public function m46(): void;

    public function m47(): void;

    public function m48(): void;

    public function m49(): void;

    public function m50(): void;
}

/**
 * 50 public methods on an enum, which PHPMD also leaves alone. An enum cannot
 * declare properties at all, so its public surface is methods and cases only.
 */
enum WideEnum
{
    case First;
    case Second;

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

    public function m46(): void
    {
    }

    public function m47(): void
    {
    }

    public function m48(): void
    {
    }

    public function m49(): void
    {
    }

    public function m50(): void
    {
    }
}

/**
 * A slim host returning an anonymous class of 44 public methods. Each scope is
 * counted on its own - the host has 1 public member, the anonymous class 44 -
 * so neither reaches the threshold. Were the nested members folded into the
 * host, the host would count 45 and be reported.
 */
class HostOfANarrowAnonymousClass
{
    public function make(): object
    {
        return new class {
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
        };
    }
}

/**
 * 50 local variables inside a single public method. A local is not a property,
 * so the count here is 1.
 */
class LocalsAreNotProperties
{
    public function calculate(): int
    {
        $local1 = 1;
        $local2 = 2;
        $local3 = 3;
        $local4 = 4;
        $local5 = 5;
        $local6 = 6;
        $local7 = 7;
        $local8 = 8;
        $local9 = 9;
        $local10 = 10;
        $local11 = 11;
        $local12 = 12;
        $local13 = 13;
        $local14 = 14;
        $local15 = 15;
        $local16 = 16;
        $local17 = 17;
        $local18 = 18;
        $local19 = 19;
        $local20 = 20;
        $local21 = 21;
        $local22 = 22;
        $local23 = 23;
        $local24 = 24;
        $local25 = 25;
        $local26 = 26;
        $local27 = 27;
        $local28 = 28;
        $local29 = 29;
        $local30 = 30;
        $local31 = 31;
        $local32 = 32;
        $local33 = 33;
        $local34 = 34;
        $local35 = 35;
        $local36 = 36;
        $local37 = 37;
        $local38 = 38;
        $local39 = 39;
        $local40 = 40;
        $local41 = 41;
        $local42 = 42;
        $local43 = 43;
        $local44 = 44;
        $local45 = 45;
        $local46 = 46;
        $local47 = 47;
        $local48 = 48;
        $local49 = 49;
        $local50 = 50;
        return $local1 + $local2 + $local3 + $local4 + $local5 + $local6 + $local7 + $local8 + $local9 + $local10 + $local11 + $local12 + $local13 + $local14 + $local15 + $local16 + $local17 + $local18 + $local19 + $local20 + $local21 + $local22 + $local23 + $local24 + $local25 + $local26 + $local27 + $local28 + $local29 + $local30 + $local31 + $local32 + $local33 + $local34 + $local35 + $local36 + $local37 + $local38 + $local39 + $local40 + $local41 + $local42 + $local43 + $local44 + $local45 + $local46 + $local47 + $local48 + $local49 + $local50;
    }
}
