<?php

namespace Fixtures\NoLogic;

class OnlyPropertyAssignments
{
    private int $a;
    private int $b;

    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}

class EmptyConstructor
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

class PromotedPlusBodyAssignments
{
    private int $sum;

    public function __construct(
        private int $x,
        private int $y,
    ) {
        $this->sum = $x;
    }
}

class DelegatesToParent extends PromotedPropertiesOnly
{
    public function __construct()
    {
        parent::__construct(1, 'y');
    }
}

class DefaultedAssignmentRightHandSides
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

class SubscriptPropertyWrites
{
    private array $arr;
    private array $cfg;

    public function __construct(int $a, string $b)
    {
        $this->arr[] = $a;
        $this->cfg['key'] = $b;
    }
}
