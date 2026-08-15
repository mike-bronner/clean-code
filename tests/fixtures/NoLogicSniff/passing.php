<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Constructors.NoLogic.
 *
 * Two things keep this file discriminating:
 *
 *   1. The compliant form of the construct the sniff registers on — a
 *      constructor whose body only assigns to its own properties, in every
 *      spelling the standard allows: plain assignment, promoted parameters,
 *      promoted parameters mixed with body assignments, a `??`/ternary default
 *      on the right-hand side, a subscript write to an own property, an
 *      exact `parent::__construct(...)` delegation, and a body with no
 *      statements at all.
 *   2. Every near-miss shape the sniff must stay silent on: a plain function
 *      named `__construct` at file scope, a method whose name merely resembles
 *      `__construct`, an ordinary method full of logic, an abstract and an
 *      interface constructor with no body, and logic inside a closure, an
 *      arrow function and an anonymous class declared on an assignment's
 *      right-hand side.
 *
 * Dropping any one of the sniff's guards reddens this file.
 */

/**
 * A free function named __construct is legal PHP but is not a class
 * constructor: the OO-scope guard has to skip it, logic and all.
 */
function __construct(int $a): void
{
    doSomething($a);

    if ($a > 0) {
        echo $a;
    }
}

final class OnlyPropertyAssignments
{
    private int $a;

    private int $b;

    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}

final class EmptyConstructor
{
    public function __construct()
    {
    }
}

class PromotedPropertiesOnly
{
    public function __construct(
        private int $x,
        private string $y,
    ) {
    }
}

final class PromotedPlusBodyAssignments
{
    private int $sum;

    public function __construct(
        private int $x,
        private int $y,
    ) {
        $this->sum = $x;
    }
}

final class DelegatesToParent extends PromotedPropertiesOnly
{
    public function __construct()
    {
        parent::__construct(1, 'y');
    }
}

final class DefaultedAssignmentRightHandSides
{
    private int $a;

    private string $b;

    private array $c;

    public function __construct(?int $a, ?string $b, ?array $c)
    {
        $this->a = $a ?? 0;
        $this->b = $b ? 'set' : 'unset';
        $this->c = $c['nested'] ?? [];
    }
}

final class SubscriptPropertyWrites
{
    private array $arr;

    private array $cfg;

    public function __construct(int $a, string $b)
    {
        $this->arr[] = $a;
        $this->cfg['key'] = $b;
    }
}

/**
 * The right-hand side is never inspected, so a declaration that merely *holds*
 * logic until someone calls it is an assignment like any other.
 */
final class DeferredLogicOnRightHandSide
{
    private $callback;

    private $doubler;

    private $collaborator;

    public function __construct(int $a)
    {
        $this->callback = function () use ($a): int {
            if ($a > 0) {
                return doSomething($a);
            }

            return 0;
        };
        $this->doubler = fn (int $v): int => $v * 2;
        $this->collaborator = new class {
            public function run(): void
            {
                doSomething(1);
            }
        };
    }
}

final class NeighbouringDeclarations
{
    private int $a;

    public function __constructor(int $a): void
    {
        doSomething($a);
    }

    public function __construct(int $a)
    {
        $this->a = $a;
    }

    public function configure(int $a): void
    {
        if ($a > 0) {
            doSomething($a);
        }

        foreach ([1, 2] as $x) {
            doSomething($x);
        }
    }
}

abstract class AbstractConstructor
{
    abstract public function __construct(int $a);
}

interface DeclaresConstructor
{
    public function __construct(int $a);
}

trait AssignsInConstructor
{
    private int $a;

    public function __construct(int $a)
    {
        $this->a = $a;
    }
}
