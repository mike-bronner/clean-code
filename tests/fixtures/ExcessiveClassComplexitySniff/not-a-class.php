<?php

declare(strict_types=1);

namespace App\Fixtures;

// PHPMD's rule is ClassAware, so it speaks about named classes and nothing
// else. Every shape below carries a weighted method count far past the default
// maximum of 50 and must still produce no violation:
//
// - a trait, and the class that uses it (the methods score against the trait,
//   and PDepend scores the using class 0);
// - an interface with 55 methods, each worth 1;
// - an enum;
// - an anonymous class, which is a separate token from a named one;
// - a named function declared inside a method, which PDepend measures as its
//   own artifact rather than as part of the method.
//
// The two holder classes are named classes, so the sniff does look at them —
// they must come out at 1, not at the 60 their nested declaration carries.

trait HugeTrait
{
    /**
     * Worth 60: 1 for the method plus 59 boolean operators.
     */
    public function huge(bool $flag): bool
    {
        return $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag;
    }
}

class UsesHugeTrait
{
    use HugeTrait;
}

interface HugeInterface
{
    public function m01(): void;
    public function m02(): void;
    public function m03(): void;
    public function m04(): void;
    public function m05(): void;
    public function m06(): void;
    public function m07(): void;
    public function m08(): void;
    public function m09(): void;
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
    public function m51(): void;
    public function m52(): void;
    public function m53(): void;
    public function m54(): void;
    public function m55(): void;
}

enum HugeEnum: string
{
    case First = 'first';
    case Second = 'second';

    /**
     * Worth 60: 1 for the method plus 59 boolean operators.
     */
    public function huge(bool $flag): bool
    {
        return $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag && $flag
            && $flag && $flag && $flag && $flag;
    }
}

class HoldsHugeAnonymousClass
{
    public function make(): object
    {
        return new class {
            public const YES = true;

            // Outside every method the anonymous class holds, so skipping the
            // methods alone does not keep it out of the enclosing count.
            public bool $flags = self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES && self::YES;

            /**
             * Worth 60: 1 for the method plus 59 boolean operators.
             */
            public function huge(bool $flag): bool
            {
                return $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag && $flag
                    && $flag && $flag && $flag && $flag;
            }
        };
    }
}

class HoldsHugeNestedFunction
{
    public function declareIt(): void
    {
        function hugeNestedFunction(bool $flag): bool
        {
            return $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag && $flag
                && $flag && $flag && $flag && $flag;
        }
    }
}

