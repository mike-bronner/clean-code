<?php

/**
 * The three shapes where this sniff deliberately reports and PHPMD 2.15.0 does
 * not. Each is a PDepend modelling gap rather than a decision PHPMD's rule
 * documents; docs/phpmd/codesize-excessivepubliccount.md records the measured
 * PHPMD output for this exact file.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Fixtures\ExcessivePublicCount;

/** 45 public properties on a trait. PDepend never counts a trait's properties. */
trait PublicPropertiesOnlyTrait
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
    public int $p26 = 26;
    public int $p27 = 27;
    public int $p28 = 28;
    public int $p29 = 29;
    public int $p30 = 30;
    public int $p31 = 31;
    public int $p32 = 32;
    public int $p33 = 33;
    public int $p34 = 34;
    public int $p35 = 35;
    public int $p36 = 36;
    public int $p37 = 37;
    public int $p38 = 38;
    public int $p39 = 39;
    public int $p40 = 40;
    public int $p41 = 41;
    public int $p42 = 42;
    public int $p43 = 43;
    public int $p44 = 44;
    public int $p45 = 45;
}

/**
 * A constructor promoting 44 public properties, plus itself: 45 public members.
 * PDepend does not model promoted parameters as properties, so PHPMD counts 1.
 */
class PromotesItsProperties
{
    public function __construct(
        public int $promoted1,
        public int $promoted2,
        public int $promoted3,
        public int $promoted4,
        public int $promoted5,
        public int $promoted6,
        public int $promoted7,
        public int $promoted8,
        public int $promoted9,
        public int $promoted10,
        public int $promoted11,
        public int $promoted12,
        public int $promoted13,
        public int $promoted14,
        public int $promoted15,
        public int $promoted16,
        public int $promoted17,
        public int $promoted18,
        public int $promoted19,
        public int $promoted20,
        public int $promoted21,
        public int $promoted22,
        public int $promoted23,
        public int $promoted24,
        public int $promoted25,
        public int $promoted26,
        public int $promoted27,
        public int $promoted28,
        public int $promoted29,
        public int $promoted30,
        public int $promoted31,
        public int $promoted32,
        public int $promoted33,
        public int $promoted34,
        public int $promoted35,
        public int $promoted36,
        public int $promoted37,
        public int $promoted38,
        public int $promoted39,
        public int $promoted40,
        public int $promoted41,
        public int $promoted42,
        public int $promoted43,
        public int $promoted44,
    ) {
    }
}

/**
 * A slim host returning an anonymous class of 45 public methods. The anonymous
 * class is its own scope and is reported on its own declaration line; the host,
 * with one public method, is not.
 */
class HostOfAnExcessiveAnonymousClass
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

            public function m45(): void
            {
            }
        };
    }
}
