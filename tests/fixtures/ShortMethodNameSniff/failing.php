<?php

/**
 * Violating input for CleanCode.Naming.ShortMethodName.
 *
 * One violation per declaration, in each shape PHPMD's rule reaches: a global
 * function (PHPMD's FunctionAware half), and methods on a class, an
 * interface, an abstract class, a trait and an enum (the MethodAware half).
 * A static method, a return-by-reference method and a method named with a
 * semi-reserved word are here because each is a different way the name token
 * could be located wrongly.
 *
 * Every line below was confirmed to be reported by phpmd 2.15 running
 * rulesets/naming.xml/ShortMethodName at its defaults.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortMethodName;

function ab(): void
{
}

interface I
{
    public function ix(): void;
}

abstract class Base
{
    abstract public function ct(): void;

    public static function st(): void
    {
    }

    public function &rf(): void
    {
    }

    // A semi-reserved word used as a method name, legal since PHP 7.0. PHPCS
    // re-tokenises it to T_STRING, so it is measured like any other name.
    public function do(): void
    {
    }
}

class Thing extends Base
{
    public function ct(): void
    {
    }

    public function a(): void
    {
    }

    public function declaresANestedFunction(): void
    {
        // A function declared inside a method body. pdepend collects it, so
        // both tools report it — this is parity, not a divergence.
        function nf(): void
        {
        }
    }
}

trait T
{
    public function tt(): void
    {
    }
}

enum E
{
    public function ee(): void
    {
    }
}
