<?php

declare(strict_types=1);

namespace App;

class Calculator
{
    private array $handlers = [];

    public function apply(int $param): int
    {
        if ($param === 42) {
            $param = 23;
        }

        return $param;
    }

    public function evaluate(string $expression): mixed
    {
        $handler = $this->handlers[$expression];

        return $handler();
    }
}
