<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

interface Shape
{
    public function area(): float;
}

final class Circle implements Shape
{
    public function __construct(private float $radius)
    {
    }

    public function area(): float
    {
        return $this->radius * $this->radius * 3.141592653589793;
    }
}

final class Square implements Shape
{
    public function __construct(private float $side)
    {
    }

    public function area(): float
    {
        return $this->side * $this->side;
    }
}

final class Renderer
{
    /**
     * The parameter declares the type, so nothing has to ask what it is.
     */
    public function render(Shape $shape): string
    {
        return number_format($shape->area(), 2);
    }

    /**
     * Branching on data the object exposes is not type introspection.
     */
    public function label(Shape $shape): string
    {
        if ($shape->area() > 100.0) {
            return 'large';
        } elseif ($shape->area() > 50.0) {
            return 'big';
        }

        return match (true) {
            $shape->area() > 10.0 => 'medium',
            $shape->area() > 1.0, $shape->area() > 0.5 => 'small',
            default => 'tiny',
        };
    }

    /**
     * Behaviour that varies by type belongs on the object itself.
     */
    public function total(Shape ...$shapes): float
    {
        $total = 0.0;

        foreach ($shapes as $shape) {
            $total += $shape->area();
        }

        return $total;
    }
}
