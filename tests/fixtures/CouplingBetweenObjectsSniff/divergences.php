<?php

/**
 * The shapes where this sniff and a live PHPMD 2.15.0 run disagree, other than
 * the unused import pinned in imports.php.
 *
 * Free of `use` imports on purpose, so nothing here is charged a dependency the
 * class body does not name.
 */

declare(strict_types=1);

namespace Div;

/**
 * PHPMD charges the anonymous class's three dependencies to the host and never
 * reports the anonymous class itself. Here each scope is counted on its own, so
 * the host depends on nothing and the anonymous class on three.
 */
class AnonymousHost
{
    public function m(): void
    {
        $inner = new class {
            public function inner(\Div\Dep\A $a, \Div\Dep\B $b): \Div\Dep\C
            {
                return $b;
            }
        };
    }
}

/**
 * PDepend models `mixed` and `object` as class types, so PHPMD counts two
 * dependencies here. Neither names a type, so neither is counted.
 */
class MixedAndObject
{
    public function m(mixed $a, object $b): void
    {
    }
}

/**
 * PDepend reads `@var`, `@return`, and `@throws`, so PHPMD counts three
 * dependencies here. This sniff reads declarations only, and there are none —
 * a shape rules.xml already rejects through
 * SlevomatCodingStandard.TypeHints.PropertyTypeHint and its siblings.
 */
class DocblockOnly
{
    /**
     * @var \Div\Dep\Documented
     */
    private $held;

    /**
     * @return \Div\Dep\Returned
     *
     * @throws \Div\Dep\Thrown
     */
    public function m()
    {
        return $this->held;
    }
}
