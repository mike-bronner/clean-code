<?php

/**
 * Compliant code, and the near-miss shapes the sniff has to stay silent on.
 *
 * The first class sits one dependency below the default threshold while naming
 * a type through every counted source at once, so a sniff that double-counted
 * any of them would speak up here. Everything after it is a shape that must
 * contribute nothing, however widely it names types: a trait, an interface, and
 * an enum are not classes and are never checked at all, and a nested anonymous
 * class is a scope of its own rather than part of its host.
 *
 * Written without `use` imports on purpose. An import is file-wide, so one
 * added here would be charged to every class in the file; imports.php carries
 * the import shapes.
 */

declare(strict_types=1);

namespace Pass;

/**
 * Twelve distinct dependencies, one below the inclusive default of 13, reached
 * through every source the sniff counts.
 */
class TwelveDependencies
{
    private \Pass\Dep\D01 $held;

    public function __construct(private \Pass\Dep\D02 $promoted)
    {
    }

    public function m(\Pass\Dep\D03 $a, \Pass\Dep\D04|\Pass\Dep\D05 $b): ?\Pass\Dep\D06
    {
        $made = new \Pass\Dep\D07();
        \Pass\Dep\D08::make();
        $value = \Pass\Dep\D09::VALUE;
        $name = \Pass\Dep\D10::class;

        if ($made instanceof \Pass\Dep\D11) {
            return null;
        }

        try {
            $this->m($a, $b);
        } catch (\Pass\Dep\D12 $e) {
        }

        return null;
    }
}

/**
 * A trait naming far more than the threshold. PHPMD's rule is `ClassAware`, and
 * a live 2.15.0 run reports nothing for a trait.
 */
trait WideTrait
{
    public function m(
        \Pass\Dep\T01 $a,
        \Pass\Dep\T02 $b,
        \Pass\Dep\T03 $c,
        \Pass\Dep\T04 $d,
        \Pass\Dep\T05 $e,
        \Pass\Dep\T06 $f,
        \Pass\Dep\T07 $g,
        \Pass\Dep\T08 $h,
        \Pass\Dep\T09 $i,
        \Pass\Dep\T10 $j,
        \Pass\Dep\T11 $k,
        \Pass\Dep\T12 $l,
        \Pass\Dep\T13 $m,
        \Pass\Dep\T14 $n,
        \Pass\Dep\T15 $o
    ): void {
    }
}

/**
 * An interface naming far more than the threshold, and never checked either.
 */
interface WideInterface
{
    public function m(
        \Pass\Dep\I01 $a,
        \Pass\Dep\I02 $b,
        \Pass\Dep\I03 $c,
        \Pass\Dep\I04 $d,
        \Pass\Dep\I05 $e,
        \Pass\Dep\I06 $f,
        \Pass\Dep\I07 $g,
        \Pass\Dep\I08 $h,
        \Pass\Dep\I09 $i,
        \Pass\Dep\I10 $j,
        \Pass\Dep\I11 $k,
        \Pass\Dep\I12 $l,
        \Pass\Dep\I13 $m,
        \Pass\Dep\I14 $n,
        \Pass\Dep\I15 $o
    ): void;
}

/**
 * An enum naming far more than the threshold, and never checked either.
 */
enum WideEnum
{
    case First;

    public function m(
        \Pass\Dep\E01 $a,
        \Pass\Dep\E02 $b,
        \Pass\Dep\E03 $c,
        \Pass\Dep\E04 $d,
        \Pass\Dep\E05 $e,
        \Pass\Dep\E06 $f,
        \Pass\Dep\E07 $g,
        \Pass\Dep\E08 $h,
        \Pass\Dep\E09 $i,
        \Pass\Dep\E10 $j,
        \Pass\Dep\E11 $k,
        \Pass\Dep\E12 $l,
        \Pass\Dep\E13 $m,
        \Pass\Dep\E14 $n,
        \Pass\Dep\E15 $o
    ): void {
    }
}

/**
 * A host with no dependencies of its own around an anonymous class that has
 * twelve. Neither reaches the threshold, because the two scopes are counted
 * apart rather than added together.
 */
class HostOfANarrowAnonymousClass
{
    public function m(): void
    {
        $inner = new class {
            private \Pass\Dep\A01 $held;

            public function inner(\Pass\Dep\A02 $a, \Pass\Dep\A03 $b): \Pass\Dep\A04
            {
                $made = new \Pass\Dep\A05();
                \Pass\Dep\A06::make();
                $value = \Pass\Dep\A07::VALUE;
                $name = \Pass\Dep\A08::class;

                if ($made instanceof \Pass\Dep\A09) {
                    return $made;
                }

                try {
                    $this->inner($a, $b);
                } catch (\Pass\Dep\A10 $e) {
                }

                $closure = function (\Pass\Dep\A11 $c) {
                    return $c;
                };
                $arrow = fn (\Pass\Dep\A12 $d) => $d;

                return $made;
            }
        };
    }
}

/**
 * A plain function naming far more than the threshold. PHPMD reports nothing
 * outside a class, and neither does this sniff.
 */
function wideFunction(
    \Pass\Dep\F01 $a,
    \Pass\Dep\F02 $b,
    \Pass\Dep\F03 $c,
    \Pass\Dep\F04 $d,
    \Pass\Dep\F05 $e,
    \Pass\Dep\F06 $f,
    \Pass\Dep\F07 $g,
    \Pass\Dep\F08 $h,
    \Pass\Dep\F09 $i,
    \Pass\Dep\F10 $j,
    \Pass\Dep\F11 $k,
    \Pass\Dep\F12 $l,
    \Pass\Dep\F13 $m,
    \Pass\Dep\F14 $n,
    \Pass\Dep\F15 $o
): void {
}
