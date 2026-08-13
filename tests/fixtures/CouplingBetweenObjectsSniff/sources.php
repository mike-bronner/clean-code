<?php

/**
 * One class per counted dependency source, each naming exactly one type, and
 * one class per shape that must contribute nothing.
 *
 * Deliberately free of `use` imports: an import is file-wide, so it would be
 * charged to every class here and flatten the distinction this fixture exists
 * to draw. The import shapes live in imports.php instead.
 */

declare(strict_types=1);

namespace Src;

class ParameterTypes
{
    public function m(\Src\Dep\Parameter $a): void
    {
    }
}

class PropertyTypes
{
    private \Src\Dep\Property $value;
}

class ReturnTypes
{
    public function m(): \Src\Dep\Returned
    {
        return $this->m();
    }
}

class Instantiations
{
    public function m(): void
    {
        $made = new \Src\Dep\Instantiated();
    }
}

class StaticCalls
{
    public function m(): void
    {
        \Src\Dep\StaticTarget::make();
    }
}

class StaticConstants
{
    public function m(): void
    {
        $value = \Src\Dep\ConstantHolder::VALUE;
    }
}

class ClassConstantReferences
{
    public function m(): void
    {
        $name = \Src\Dep\Named::class;
    }
}

class CaughtTypes
{
    public function m(): void
    {
        try {
            $this->m();
        } catch (\Src\Dep\Caught $e) {
        }
    }
}

class InstanceofTypes
{
    public function m($value): void
    {
        if ($value instanceof \Src\Dep\Tested) {
            return;
        }
    }
}

class PromotedProperties
{
    public function __construct(private \Src\Dep\Promoted $promoted)
    {
    }
}

class ClosureParameters
{
    public function m(): void
    {
        $closure = function (\Src\Dep\ClosureParameter $a) {
            return $a;
        };
    }
}

class ArrowFunctionParameters
{
    public function m(): void
    {
        $arrow = fn (\Src\Dep\ArrowParameter $a) => $a;
    }
}

/**
 * One type, six spellings — a property, a parameter, a return, an
 * instantiation, a static call, and a catch. Distinct types are what is
 * counted, so this class depends on exactly one thing.
 */
class DuplicateReferences
{
    private \Src\Dep\Repeated $held;

    public function m(\Src\Dep\Repeated $a): \Src\Dep\Repeated
    {
        $made = new \Src\Dep\Repeated();
        \Src\Dep\Repeated::make();

        try {
            $this->m($a);
        } catch (\Src\Dep\Repeated $e) {
        }

        return $a;
    }
}

/**
 * Every scalar and pseudo type PHP can declare. None of them names a type a
 * class can depend on — including `mixed` and `object`, which PDepend models as
 * class types and PHPMD therefore counts.
 */
class ScalarTypesOnly
{
    private int $number;

    private string $text;

    public function m(
        int $a,
        float $b,
        string $c,
        bool $d,
        array $e,
        iterable $f,
        callable $g,
        mixed $h,
        object $i,
        false $j,
        true $k,
        null $l
    ): void {
    }

    public function nothing(): never
    {
        exit;
    }
}

/**
 * The class's own name, `self`, `static`, `parent`, and the `extends` and
 * `implements` clauses. PDepend excludes a type that is a subtype of the
 * declaring type or vice versa; the clauses sit outside the class body, so the
 * walk never reaches them.
 */
class SelfReferencesOnly extends \Src\Dep\Base implements \Src\Dep\Contract
{
    private ?SelfReferencesOnly $next = null;

    public function m(self $a, SelfReferencesOnly $b, \Src\SelfReferencesOnly $c): static
    {
        $made = new SelfReferencesOnly();
        self::helper();
        static::helper();
        parent::helper();

        return $this;
    }

    public static function helper(): void
    {
    }
}

/**
 * A `use` of a trait is not an import, and its adaptation block names methods
 * of a type this class never touches. PHPMD counts nothing here either.
 */
class TraitUsers
{
    use \Src\Dep\FirstTrait;
    use \Src\Dep\SecondTrait {
        \Src\Dep\SecondTrait::collide insteadof \Src\Dep\FirstTrait;
        \Src\Dep\SecondTrait::collide as renamed;
    }
}

/**
 * An attribute names a type but is metadata, not coupling — PHPMD counts none
 * of these.
 */
#[\Src\Dep\ClassAttribute]
class AttributeHolders
{
    #[\Src\Dep\PropertyAttribute]
    private int $value = 0;

    #[\Src\Dep\MethodAttribute(\Src\Dep\ArgumentAttribute::class)]
    public function m(): void
    {
    }
}

/**
 * A dynamic reference names no type at all: the operand is a variable, and
 * which class it holds is not knowable from the tokens.
 */
class DynamicReferences
{
    public function m(string $class, $object, $other): void
    {
        $made = new $class();
        $object::make();

        if ($object instanceof $other) {
            return;
        }
    }
}
